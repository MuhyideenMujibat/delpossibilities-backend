<?php

namespace App\Http\Controllers;

use App\Models\ProductOrder;
use App\Models\Refill;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RefillController extends Controller
{
    public function store(Request $request)
    {
        $subscriber = $request->user()->subscribers()->where('status', 'active')->first();

        if (! $subscriber) {
            throw ValidationException::withMessages([
                'subscriber' => ['You do not have an active subscription to request a refill against.'],
            ]);
        }

        // Kg — not the calendar window — is what actually gates whether a
        // subscriber can keep refilling: their subscription payment covers
        // a fixed kg allotment (Subscriber::activate), drawn down as each
        // refill is delivered (see update() below), regardless of how many
        // days remain in the session/semester.
        if ((float) $subscriber->remaining_kg <= 0) {
            throw ValidationException::withMessages([
                'kg' => ["You've used up your subscription's kg allowance for this ".$subscriber->plan->package_type.'.'],
            ]);
        }

        $validated = $request->validate([
            'kg' => ['required', 'numeric', 'min:0.01', 'max:'.$subscriber->remaining_kg],
            'product_order_id' => ['nullable', 'integer', 'exists:product_orders,id'],
        ]);

        // Idempotency guard, same reasoning as OrderController::store: a
        // double-click shouldn't queue two refill requests.
        $pending = $subscriber->refills()->where('status', 'pending')->latest()->first();
        if ($pending) {
            return response()->json($pending, 200);
        }

        // An Eazy Market / Gas Services cart, if the student attached one —
        // must already be PAID (status 'approved', not the payment-pending
        // state) before it can be attached. A refill has no payment step of
        // its own, so there's nothing here to fold an unpaid cart's charge
        // into the way a bundled gas Order does — the cart must be checked
        // out and paid for on its own first (see Cart.jsx), and only then
        // linked here purely for delivery tracking (see update() below).
        // Never attach an unpaid one: that would let this endpoint mark a
        // cart "fulfillable" without a real charge ever happening.
        $productOrder = null;
        if (! empty($validated['product_order_id'])) {
            $productOrder = ProductOrder::where('id', $validated['product_order_id'])
                ->where('user_id', $request->user()->id)
                ->where('status', 'approved')
                ->first();

            if (! $productOrder) {
                throw ValidationException::withMessages([
                    'product_order_id' => ['This cart order must be paid for before it can be attached to a refill.'],
                ]);
            }

            // A paid standalone cart can become eligible here (status
            // 'approved') the same way a cart bundled into a gas Order
            // does once that order's payment clears — guard against
            // attaching one that's already riding on an Order's delivery.
            if ($productOrder->isLinked()) {
                throw ValidationException::withMessages([
                    'product_order_id' => ['This cart order is already attached to another delivery.'],
                ]);
            }
        }

        $refill = $subscriber->refills()->create([
            'requested_at' => now(),
            'kg_requested' => $validated['kg'],
            'product_order_id' => $productOrder?->id,
            'status' => 'pending',
        ]);

        return response()->json($refill, 201);
    }

    // Admin: defaults to pending refills (the actionable queue), but can be
    // widened with ?status=all or a specific status.
    public function index(Request $request)
    {
        $query = Refill::with(['subscriber.user', 'subscriber.plan']);

        $status = $request->input('status', 'pending');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return response()->json($query->latest()->get());
    }

    // Same pending -> approved -> picked_up -> delivered pipeline as
    // OrderController::updateStatus, plus the cancel escape hatch refills
    // have always had (a refill has no payment gate, so — unlike an order —
    // every one of these transitions, including the first, is a manual
    // admin action rather than something a webhook advances automatically).
    public function update(Request $request, Refill $refill)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'picked_up', 'delivered', 'cancelled'])],
            'kg_delivered' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        // Idempotent no-op (repeated click/request), and a finished refill
        // (delivered/cancelled) can't be reopened.
        if (in_array($refill->status, ['delivered', 'cancelled'], true) || $refill->status === $validated['status']) {
            return response()->json($refill);
        }

        $isDelivering = $validated['status'] === 'delivered';
        // Defaults to what the student originally asked for; an admin can
        // still override for a partial delivery. Clamped to the
        // subscriber's remaining_kg so this can never push the balance
        // negative — by construction it shouldn't (only one refill is ever
        // pending at a time, see store()), but this is cheap insurance.
        $kgDelivered = $isDelivering
            ? min(
                (float) ($validated['kg_delivered'] ?? $refill->kg_requested ?? $refill->subscriber->plan->cylinder_kg),
                (float) $refill->subscriber->remaining_kg
            )
            : $refill->kg_delivered;

        $refill->update([
            'status' => $validated['status'],
            'delivered_at' => $isDelivering ? now() : $refill->delivered_at,
            'kg_delivered' => $kgDelivered,
        ]);

        // Refills are covered entirely by the subscription payment — no
        // per-refill charge, just this deduction from the kg allotment.
        if ($isDelivering) {
            $refill->subscriber->update([
                'remaining_kg' => max(0, (float) $refill->subscriber->remaining_kg - $kgDelivered),
            ]);
        }

        // A linked cart rides on this same delivery trip — its fulfillment
        // status follows the refill's, one delivery tracked in one place,
        // rather than the student having to watch two separate pages for
        // one trip. Payment for the cart is unaffected — that already
        // happened (or is happening) via its own pay()/verify-payment,
        // entirely independent of the refill, which is never charged.
        if ($refill->product_order_id) {
            $refill->productOrder?->update(['status' => $validated['status']]);
        }

        // Loaded so the frontend's optimistic merge (see AdminRefills.jsx)
        // picks up the fresh remaining_kg immediately instead of waiting on
        // the next poll.
        return response()->json($refill->load('subscriber'));
    }
}
