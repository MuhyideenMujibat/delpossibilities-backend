<?php

namespace App\Http\Controllers;

use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\ProductOrder;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'kg' => ['required', 'numeric', 'min:0.01'],
            'location_type' => ['required', Rule::in(['hostel', 'off_campus'])],
            'hostel_address' => ['required', 'string', 'max:255'],
            'cylinder_image' => ['nullable', 'image', 'max:5120'],
            'delivery_zone_id' => ['nullable', 'required_if:location_type,off_campus', 'integer', 'exists:delivery_zones,id'],
            'product_order_id' => ['nullable', 'integer', 'exists:product_orders,id'],
            'attached_product_order_id' => ['nullable', 'integer', 'different:product_order_id', 'exists:product_orders,id'],
        ]);

        $user = $request->user();

        if (! $user->cylinder_image && ! $request->hasFile('cylinder_image')) {
            throw ValidationException::withMessages([
                'cylinder_image' => ['Please upload your cylinder image before placing an order.'],
            ]);
        }

        // Idempotency guard: a double-click or a retried request (e.g. a
        // slow response the client times out on and resubmits) shouldn't
        // create two identical orders. If the same user just created a
        // matching pending order in the last 20 seconds, hand that back
        // instead of creating a duplicate.
        $duplicate = $user->orders()
            ->where('status', 'pending')
            ->where('kg', $validated['kg'])
            ->where('hostel_address', $validated['hostel_address'])
            ->where('created_at', '>=', now()->subSeconds(20))
            ->latest()
            ->first();

        if ($duplicate) {
            return response()->json($duplicate, 200);
        }

        $setting = Setting::current();
        $pricePerKg = $setting->offer_active && $setting->offer_price_per_kg
            ? $setting->offer_price_per_kg
            : $setting->price_per_kg;

        // Off-campus delivery is zone-based (student picks a named zone,
        // each with its own admin-set fee) rather than the old flat
        // Setting::off_campus_delivery_fee. On-campus stays a flat fee.
        $deliveryZone = null;
        if ($validated['location_type'] === 'off_campus') {
            $deliveryZone = DeliveryZone::where('is_active', true)->find($validated['delivery_zone_id']);

            if (! $deliveryZone) {
                throw ValidationException::withMessages([
                    'delivery_zone_id' => ['Please select a valid delivery zone.'],
                ]);
            }
        }

        $deliveryFee = $validated['location_type'] === 'off_campus'
            ? (float) $deliveryZone->fee
            : ($setting->delivery_fee ?? 0);

        // Referral discount — an admin-configurable flat percentage off this
        // order's gas cost (Setting::referral_discount_percent, see
        // AdminSettings' Referral Rewards section), spending one of the
        // student's coupons. Earned only by referring someone who then pays
        // for a 3 kg+ gas order (see User::grantReferralRewardIfEligible).
        // Its own line item, like loyalty; auto-applied whenever a coupon is
        // available, decremented on order creation.
        $referralDiscountAmount = 0.0;
        if ((int) $user->referral_discount_available > 0) {
            $referralDiscountAmount = round(
                (float) $validated['kg'] * (float) $pricePerKg * ($setting->referralDiscountPercent() / 100),
                2
            );
        }

        // An Eazy Market / Gas Services cart (see ProductOrderController) can
        // ride along with this order in TWO independent ways, and a student
        // can use both at once:
        //  - `product_order_id`: a still-unpaid cart bundled in — its total
        //    folds additively into this order's own Paystack charge.
        //  - `attached_product_order_id`: a cart already paid for standalone
        //    (e.g. via Cart.jsx's "Checkout Cart Only") — just tagged to this
        //    delivery for fulfilment tracking, nothing folded into the charge.
        // Keeping them separate is what lets a student pay for one shop item,
        // attach it here, AND still bundle their other unpaid cart items on
        // the same refill without either dropping off the order.
        $productOrder = null;
        $productOrderTotal = 0;
        if (! empty($validated['product_order_id'])) {
            $productOrder = ProductOrder::where('id', $validated['product_order_id'])
                ->where('user_id', $user->id)
                ->whereIn('status', ['pending', 'approved'])
                ->first();

            if (! $productOrder) {
                throw ValidationException::withMessages([
                    'product_order_id' => ['This cart order is no longer available to attach.'],
                ]);
            }

            if ($productOrder->status === 'pending') {
                $productOrderTotal = (float) $productOrder->total_amount;
            } elseif ($productOrder->isLinked()) {
                throw ValidationException::withMessages([
                    'product_order_id' => ['This cart order is already attached to another delivery.'],
                ]);
            }
        }

        $attachedProductOrder = null;
        if (! empty($validated['attached_product_order_id'])) {
            $attachedProductOrder = ProductOrder::where('id', $validated['attached_product_order_id'])
                ->where('user_id', $user->id)
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

        // Loyalty discount. Once the student's running kg total
        // (loyalty_progress_kg, advanced only by paid orders — see
        // Order::applyLoyaltyProgress) reaches the admin's threshold, the
        // portion of an order BEYOND what was still needed to complete the
        // threshold is discounted, on that same order. Example, 5 kg
        // threshold: progress 4 kg, then a 4 kg order — 1 kg completes the
        // threshold, the other 3 kg gets the discount. An order that only
        // lands the total exactly on the threshold (nothing left over) isn't
        // discounted; the next order is. price_per_kg stays the normal/offer
        // rate — the discount is its own line item subtracted from the
        // total, so "gas cost" on receipts reads honestly. Applying a
        // discount resets progress to 0 (Order::applyLoyaltyProgress).
        $loyaltyDiscountApplied = false;
        $loyaltyDiscountAmount = null;

        if ($setting->loyaltyActive()) {
            $neededToComplete = max((float) $setting->loyalty_threshold_kg - (float) $user->loyalty_progress_kg, 0);
            $discountableKg = max((float) $validated['kg'] - $neededToComplete, 0);

            if ($discountableKg > 0) {
                $loyaltyDiscountAmount = round((float) $pricePerKg * $discountableKg * ((float) $setting->loyalty_discount_percent / 100), 2);
                $loyaltyDiscountApplied = true;
            }
        }

        $imagePath = $request->hasFile('cylinder_image')
            ? $request->file('cylinder_image')->store('cylinders', 'public')
            : $user->cylinder_image;

        $order = $user->orders()->create([
            'cylinder_image' => $imagePath,
            'kg' => $validated['kg'],
            'price_per_kg' => $pricePerKg,
            'loyalty_discount_applied' => $loyaltyDiscountApplied,
            'loyalty_discount_amount' => $loyaltyDiscountAmount,
            'delivery_fee' => $deliveryFee,
            'referral_credit_applied' => 0,
            'referral_discount_amount' => $referralDiscountAmount,
            'product_order_id' => $productOrder?->id,
            'attached_product_order_id' => $attachedProductOrder?->id,
            'total_amount' => round(
                max($validated['kg'] * $pricePerKg - ($loyaltyDiscountAmount ?? 0) - $referralDiscountAmount, 0)
                + $deliveryFee + $productOrderTotal, 2
            ),
            'hostel_address' => $validated['hostel_address'],
            'location_type' => $validated['location_type'],
            'delivery_zone_id' => $deliveryZone?->id,
            'status' => 'pending',
        ]);

        if ($referralDiscountAmount > 0) {
            $user->decrement('referral_discount_available');
        }

        return response()->json($order, 201);
    }

    public function myOrders(Request $request)
    {
        return response()->json(
            $request->user()->orders()
                ->with(['productOrder.items', 'attachedProductOrder.items'])
                ->latest()
                ->get()
        );
    }

    public function index()
    {
        return response()->json(
            Order::with(['user', 'productOrder.items', 'attachedProductOrder.items'])->latest()->get()
        );
    }

    public function pay(Request $request, Order $order)
    {
        $user = $request->user();

        if ($order->user_id !== $user->id) {
            abort(403, 'This order does not belong to you.');
        }

        if ($order->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['This order has already been paid for or handled.'],
            ]);
        }

        if ($order->paystack_reference && $order->payment_authorization_url) {
            return response()->json([
                'authorization_url' => $order->payment_authorization_url,
                'reference' => $order->paystack_reference,
            ]);
        }

        $reference = 'order_'.$order->id.'_'.Str::random(10);

        try {
            $response = Http::withToken(config('services.paystack.secret_key'))
                ->post('https://api.paystack.co/transaction/initialize', [
                    'email' => $user->email,
                    'amount' => (int) ($order->total_amount * 100),
                    'reference' => $reference,
                    'callback_url' => config('services.paystack.callback_url'),
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Paystack connection error: '.$e->getMessage());

            return response()->json([
                'message' => 'Unable to reach the payment provider. Please try again shortly.',
            ], 500);
        }

        if (! $response->successful() || ! $response->json('status')) {
            Log::error('Paystack initialize transaction failed', [
                'order_id' => $order->id,
                'response' => $response->json(),
            ]);

            return response()->json([
                'message' => 'Unable to initialize payment. Please try again shortly.',
            ], 500);
        }

        $order->update([
            'paystack_reference' => $reference,
            'payment_authorization_url' => $response->json('data.authorization_url'),
        ]);

        return response()->json([
            'authorization_url' => $response->json('data.authorization_url'),
            'reference' => $reference,
        ]);
    }

    // Paystack's webhook is the async, "belt and braces" confirmation path —
    // but it requires Paystack's servers to reach ours, which only works
    // once this app is deployed behind a public URL. This is the
    // synchronous counterpart: right after Paystack redirects the student
    // back, the frontend calls this so the backend asks Paystack directly
    // whether the charge actually succeeded, rather than waiting on a
    // webhook that may never arrive (e.g. in local development).
    public function verifyPayment(Request $request, Order $order)
    {
        $user = $request->user();

        if ($order->user_id !== $user->id) {
            abort(403, 'This order does not belong to you.');
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'order' => $order,
                'paystack_status' => 'success',
                'loyalty' => array_merge(
                    $order->user->loyaltySummary(),
                    ['discount_applied_to_this_order' => (bool) $order->loyalty_discount_applied]
                ),
            ]);
        }

        if (! $order->paystack_reference) {
            throw ValidationException::withMessages([
                'status' => ['No payment has been started for this order yet.'],
            ]);
        }

        try {
            $response = Http::withToken(config('services.paystack.secret_key'))
                ->get('https://api.paystack.co/transaction/verify/'.$order->paystack_reference);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Paystack verify connection error: '.$e->getMessage());

            return response()->json([
                'message' => 'Unable to reach the payment provider. Please try again shortly.',
            ], 500);
        }

        if (! $response->successful() || ! $response->json('status')) {
            Log::error('Paystack verify transaction failed', [
                'order_id' => $order->id,
                'response' => $response->json(),
            ]);

            return response()->json([
                'message' => 'Unable to verify this payment right now. Please try again shortly.',
            ], 500);
        }

        $paystackStatus = $response->json('data.status');
        $amountPaidInKobo = $response->json('data.amount');
        $expectedAmountInKobo = (int) ($order->total_amount * 100);

        if ($paystackStatus === 'success' && $amountPaidInKobo === $expectedAmountInKobo) {
            $order->update(['status' => 'approved', 'paid_at' => now()]);
            $order->notifyStatusChange();
            $order->applyLoyaltyProgress();
            $order->sendReceiptEmail();

            // A bundled cart that was still `pending` was paid for as part
            // of this exact charge — it never gets its own
            // paystack_reference (its own pay() is never called in the
            // bundled path), so mark it paid here, in lockstep with the gas
            // order it rode in on. An already-`approved` cart (attached
            // after being paid for standalone) is left untouched — it kept
            // its own real paid_at, this order never charged for it.
            if ($order->productOrder?->status === 'pending') {
                $order->productOrder->update(['status' => 'approved', 'paid_at' => $order->paid_at]);
            }

            $order->user->grantReferralRewardIfEligible((float) $order->kg);
        } elseif ($paystackStatus === 'success') {
            // Paid, but not the right amount — don't silently approve it.
            Log::warning('Paystack verify amount mismatch', [
                'order_id' => $order->id,
                'expected' => $expectedAmountInKobo,
                'paid' => $amountPaidInKobo,
            ]);
        }

        // The student's loyalty standing *after* this payment settled — so
        // the callback screen can tell them how much kg is left to unlock the
        // next reward, that a reward is now waiting, or that this order just
        // spent one.
        $loyalty = array_merge(
            $order->user->fresh()->loyaltySummary(),
            ['discount_applied_to_this_order' => (bool) $order->loyalty_discount_applied]
        );

        return response()->json([
            'order' => $order->fresh(),
            'paystack_status' => $paystackStatus,
            'loyalty' => $loyalty,
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['picked_up', 'delivered'])],
        ]);

        // Idempotent no-op: a repeated confirm (double click, retried
        // request) that lands after the first one already applied should
        // just hand back the current order instead of writing again.
        if ($order->status === $validated['status']) {
            return response()->json($order);
        }

        $order->update(['status' => $validated['status']]);
        $order->notifyStatusChange();

        // Any cart riding on this same delivery trip — bundled or attached —
        // has its fulfilment status follow the order's, one delivery tracked
        // in one place. Payment for each already happened (bundled: as part
        // of this order's single charge; attached: standalone earlier), so
        // only the status moves here.
        if ($order->product_order_id) {
            $order->productOrder?->update(['status' => $validated['status']]);
        }
        if ($order->attached_product_order_id) {
            $order->attachedProductOrder?->update(['status' => $validated['status']]);
        }

        return response()->json($order);
    }
}
