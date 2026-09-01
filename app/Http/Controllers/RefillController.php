<?php

namespace App\Http\Controllers;

use App\Models\ProductOrder;
use App\Models\Refill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RefillController extends Controller
{
    public function store(Request $request)
    {
        // The usable subscription first — an account can hold more than one
        // 'active' row (e.g. a transfer recipient who still has their own
        // old exhausted one), and picking the wrong one is what wrongly
        // told a fresh transfer recipient their allowance was "used up".
        // Fall back to any active row so the genuine "no kg left" / "no
        // subscription" messages below still fire when that's really the case.
        $subscriber = $request->user()->subscribers()->usable()->latest()->first()
            ?? $request->user()->subscribers()->where('status', 'active')->latest()->first();

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
            'attached_product_order_id' => ['nullable', 'integer', 'different:product_order_id', 'exists:product_orders,id'],
            'cylinder_image' => ['nullable', 'image', 'max:5120'],
        ]);

        // A refill is a delivery just like a normal gas order — the rider
        // needs a cylinder photo to identify at pickup. Require one (either
        // already on the profile, or supplied with this request).
        $user = $request->user();
        if (! $user->cylinder_image && ! $request->hasFile('cylinder_image')) {
            throw ValidationException::withMessages([
                'cylinder_image' => ['Please upload your cylinder image before requesting a refill.'],
            ]);
        }

        // A supplied photo both rides on this refill AND updates the profile
        // default, so "upload or update it" sticks for next time.
        if ($request->hasFile('cylinder_image')) {
            $oldImage = $user->cylinder_image;
            $cylinderImage = $request->file('cylinder_image')->store('cylinders/profile', 'public');
            $user->update(['cylinder_image' => $cylinderImage]);
            if ($oldImage) {
                Storage::disk('public')->delete($oldImage);
            }
        } else {
            $cylinderImage = $user->cylinder_image;
        }

        // Idempotency guard, same reasoning as OrderController::store: a
        // double-click shouldn't queue two refill requests.
        $pending = $subscriber->refills()->where('status', 'pending')->latest()->first();
        if ($pending) {
            return response()->json($pending, 200);
        }

        // An Eazy Market / Gas Services cart the subscriber wants on this
        // same delivery trip — parity with OrderController::store, and a
        // subscriber can use BOTH at once:
        //  - product_order_id: a cart built from the refill request itself
        //    (see MySubscription.jsx), 'pending' — linked here right away,
        //    then paid for on its own immediately after (a refill has no
        //    charge of its own to fold it into), so the cart lands on this
        //    refill whether or not that payment is finished. An 'approved'
        //    one (paid standalone earlier) is also accepted here.
        //  - attached_product_order_id: a cart ALREADY paid for standalone
        //    (Cart.jsx's "Checkout Cart Only"), just tagged onto this
        //    delivery for fulfilment tracking.
        // update() and the cancel path only ever move a cart that's
        // actually been paid for, so an abandoned 'pending' bundle can
        // never be marked fulfilled without a real charge.
        $productOrder = null;
        if (! empty($validated['product_order_id'])) {
            $productOrder = ProductOrder::where('id', $validated['product_order_id'])
                ->where('user_id', $request->user()->id)
                ->whereIn('status', ['pending', 'approved'])
                ->first();

            if (! $productOrder) {
                throw ValidationException::withMessages([
                    'product_order_id' => ['This cart order is no longer available to attach.'],
                ]);
            }

            if ($productOrder->isLinked()) {
                throw ValidationException::withMessages([
                    'product_order_id' => ['This cart order is already attached to another delivery.'],
                ]);
            }
        }

        $attachedProductOrder = null;
        if (! empty($validated['attached_product_order_id'])) {
            $attachedProductOrder = ProductOrder::where('id', $validated['attached_product_order_id'])
                ->where('user_id', $request->user()->id)
                ->where('status', 'approved')
                ->first();

            if (! $attachedProductOrder) {
                throw ValidationException::withMessages([
                    'attached_product_order_id' => ['This paid cart order is no longer available to attach.'],
                ]);
            }

            if ($attachedProductOrder->isLinked()) {
                throw ValidationException::withMessages([
                    'attached_product_order_id' => ['This cart order is already attached to another delivery.'],
                ]);
            }
        }

        $refill = $subscriber->refills()->create([
            'user_id' => $user->id,
            'requested_at' => now(),
            'kg_requested' => $validated['kg'],
            'product_order_id' => $productOrder?->id,
            'attached_product_order_id' => $attachedProductOrder?->id,
            'cylinder_image' => $cylinderImage,
            'status' => 'pending',
        ]);

        return response()->json($refill->load(['productOrder.items', 'attachedProductOrder.items']), 201);
    }

    // Admin: defaults to pending refills (the actionable queue), but can be
    // widened with ?status=all or a specific status.
    public function index(Request $request)
    {
        $query = Refill::with(['subscriber.user', 'subscriber.plan', 'productOrder.items', 'attachedProductOrder.items']);

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
        // Skip it when the kg was already reserved out of the pool at
        // transfer time (see SubscriberController::transfer, "carry").
        if ($isDelivering && ! $refill->kg_prereserved) {
            $refill->subscriber->update([
                'remaining_kg' => max(0, (float) $refill->subscriber->remaining_kg - $kgDelivered),
            ]);
        }

        // Any cart riding on this same delivery trip — bundled or attached.
        // Its payment is its own (pay()/verify-payment on the ProductOrder),
        // independent of the refill, so only a paid one ('approved'+)
        // follows the refill through the fulfilment states. On cancel,
        // detach it so it isn't stranded on a dead refill — a paid one goes
        // back to 'approved' (free to re-attach elsewhere), an unpaid
        // bundle is left 'pending' for the subscriber to finish or drop.
        foreach ([['product_order_id', $refill->productOrder], ['attached_product_order_id', $refill->attachedProductOrder]] as [$column, $cart]) {
            if (! $cart) {
                continue;
            }
            if ($validated['status'] === 'cancelled') {
                if ($cart->status !== 'pending') {
                    $cart->update(['status' => 'approved']);
                }
                $refill->update([$column => null]);
            } elseif ($cart->status !== 'pending') {
                $cart->update(['status' => $validated['status']]);
            }
        }

        // Loaded so the frontend's optimistic merge (see AdminRefills.jsx)
        // picks up the fresh remaining_kg immediately instead of waiting on
        // the next poll.
        return response()->json($refill->load(['subscriber', 'productOrder.items', 'attachedProductOrder.items']));
    }
}
