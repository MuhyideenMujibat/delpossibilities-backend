<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductOrderController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'hostel_address' => ['nullable', 'string', 'max:255'],
            'location_type' => ['nullable', 'in:hostel,off_campus'],
            'use_referral_credit' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();

        // A cart-only checkout still needs a real delivery destination even
        // though it never affects fee math — fall back to the student's
        // saved profile address, but a profile with none set (and no
        // address given in this request either) can't silently proceed.
        $hostelAddress = $validated['hostel_address'] ?? $user->hostel;
        if (! $hostelAddress) {
            throw ValidationException::withMessages([
                'hostel_address' => ['Please provide a delivery address to continue.'],
            ]);
        }

        $lineItems = [];

        foreach ($validated['items'] as $row) {
            $product = Product::where('id', $row['product_id'])->where('is_active', true)->first();

            if (! $product) {
                throw ValidationException::withMessages([
                    'items' => ['One of the items in your cart is no longer available.'],
                ]);
            }

            $variant = null;
            if (! empty($row['product_variant_id'])) {
                $variant = ProductVariant::where('id', $row['product_variant_id'])
                    ->where('product_id', $product->id)
                    ->where('is_active', true)
                    ->first();

                if (! $variant) {
                    throw ValidationException::withMessages([
                        'items' => ['One of the item options in your cart is no longer available.'],
                    ]);
                }
            }

            // Never trust a client-sent price — always the catalog's
            // current price at the moment of checkout.
            $unitPrice = $variant ? $variant->price : $product->price;
            $quantity = (int) $row['quantity'];

            $lineItems[] = [
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'product_name' => $product->name,
                'group' => $product->group,
                'category' => $product->category,
                'variant_label' => $variant?->label,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => round($unitPrice * $quantity, 2),
            ];
        }

        $subtotal = round(array_sum(array_column($lineItems, 'line_total')), 2);
        $groupTotals = ProductOrder::subtotalsByGroup($lineItems);
        $deliveryFee = ProductOrder::eazyMarketFeeForSubtotal($groupTotals['eazy_market']);

        // Same referral-credit pattern as OrderController::store() — offsets
        // only the Eazy Market delivery fee portion (Gas Services items stay
        // free regardless, per the existing rule), up to whichever is
        // smaller: the fee itself, or the student's available balance.
        $referralCreditApplied = 0;
        if ($request->boolean('use_referral_credit') && (float) $user->referral_credit_balance > 0) {
            $referralCreditApplied = min($deliveryFee, (float) $user->referral_credit_balance);
        }

        // The only place in this feature that creates more than one row per
        // request (one ProductOrder + N items) — a deliberate, narrow use
        // of a transaction in a codebase that otherwise writes exactly one
        // row per request, since a partial write here (order created, some
        // items missing) is a real and cheaply avoidable risk.
        $productOrder = DB::transaction(function () use ($user, $validated, $hostelAddress, $lineItems, $subtotal, $deliveryFee, $referralCreditApplied) {
            $productOrder = ProductOrder::create([
                'user_id' => $user->id,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'referral_credit_applied' => $referralCreditApplied,
                'total_amount' => round($subtotal + $deliveryFee - $referralCreditApplied, 2),
                'hostel_address' => $hostelAddress,
                'location_type' => $validated['location_type'] ?? $user->location_type ?? 'hostel',
                'status' => 'pending',
            ]);

            foreach ($lineItems as $item) {
                $productOrder->items()->create($item);
            }

            if ($referralCreditApplied > 0) {
                $user->decrement('referral_credit_balance', $referralCreditApplied);
            }

            return $productOrder;
        });

        return response()->json($productOrder->load('items'), 201);
    }

    public function mine(Request $request)
    {
        $productOrder = $request->user()->productOrders()
            ->with(['items', 'refill', 'attachingRefill', 'order', 'attachingOrder'])
            ->latest()
            ->first();

        return response()->json($productOrder);
    }

    // Full history for "My Shop Orders" — unlike mine() (used right after
    // checkout to confirm the one just paid for), this lists every cart
    // purchase, standalone or bundled, so a student can track any of them.
    public function myOrders(Request $request)
    {
        return response()->json(
            $request->user()->productOrders()
                ->with([
                    'items',
                    'order:id,status,product_order_id',
                    'attachingOrder:id,status,attached_product_order_id',
                    'refill:id,status,product_order_id',
                    'attachingRefill:id,status,attached_product_order_id',
                ])
                ->latest()
                ->get()
        );
    }

    public function pay(Request $request, ProductOrder $productOrder)
    {
        $user = $request->user();

        if ($productOrder->user_id !== $user->id) {
            abort(403, 'This order does not belong to you.');
        }

        if ($productOrder->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['This order has already been paid for or handled.'],
            ]);
        }

        // This is the standalone-checkout path only — a bundled cart never
        // calls its own pay(), the gas order/refill's payment covers it
        // instead — so an Eazy-Market-only cart reaching here really would
        // ship on its own, which isn't allowed. Active subscribers are
        // exempt: their whole model is paying for a cart standalone now and
        // attaching it to their next refill request later (see
        // RefillController::store), so the "companion" is their ongoing
        // subscription rather than something in this same cart/charge — but
        // only a USABLE subscription counts, an exhausted/expired one has no
        // "next refill" to ride on.
        $isActiveSubscriber = $user->subscribers()->usable()->exists();
        if (! $isActiveSubscriber && $productOrder->loadMissing('items')->isEazyMarketOnly()) {
            throw ValidationException::withMessages([
                'items' => ['Eazy Market items need a gas accessory in the same cart, or to be attached to a gas refill — they can\'t be delivered on their own.'],
            ]);
        }

        if ($productOrder->paystack_reference && $productOrder->payment_authorization_url) {
            return response()->json([
                'authorization_url' => $productOrder->payment_authorization_url,
                'reference' => $productOrder->paystack_reference,
            ]);
        }

        $reference = 'product_order_'.$productOrder->id.'_'.Str::random(10);

        try {
            $response = Http::withToken(config('services.paystack.secret_key'))
                ->post('https://api.paystack.co/transaction/initialize', [
                    'email' => $user->email,
                    'amount' => (int) ($productOrder->total_amount * 100),
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
                'product_order_id' => $productOrder->id,
                'response' => $response->json(),
            ]);

            return response()->json([
                'message' => 'Unable to initialize payment. Please try again shortly.',
            ], 500);
        }

        $productOrder->update([
            'paystack_reference' => $reference,
            'payment_authorization_url' => $response->json('data.authorization_url'),
        ]);

        return response()->json([
            'authorization_url' => $response->json('data.authorization_url'),
            'reference' => $reference,
        ]);
    }

    public function verifyPayment(Request $request, ProductOrder $productOrder)
    {
        $user = $request->user();

        if ($productOrder->user_id !== $user->id) {
            abort(403, 'This order does not belong to you.');
        }

        if ($productOrder->status !== 'pending') {
            return response()->json(['product_order' => $productOrder, 'paystack_status' => 'success']);
        }

        if (! $productOrder->paystack_reference) {
            throw ValidationException::withMessages([
                'status' => ['No payment has been started for this order yet.'],
            ]);
        }

        try {
            $response = Http::withToken(config('services.paystack.secret_key'))
                ->get('https://api.paystack.co/transaction/verify/'.$productOrder->paystack_reference);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Paystack verify connection error: '.$e->getMessage());

            return response()->json([
                'message' => 'Unable to reach the payment provider. Please try again shortly.',
            ], 500);
        }

        if (! $response->successful() || ! $response->json('status')) {
            Log::error('Paystack verify transaction failed', [
                'product_order_id' => $productOrder->id,
                'response' => $response->json(),
            ]);

            return response()->json([
                'message' => 'Unable to verify this payment right now. Please try again shortly.',
            ], 500);
        }

        $paystackStatus = $response->json('data.status');
        $amountPaidInKobo = $response->json('data.amount');
        $expectedAmountInKobo = (int) ($productOrder->total_amount * 100);

        if ($paystackStatus === 'success' && $amountPaidInKobo === $expectedAmountInKobo) {
            $productOrder->update(['status' => 'approved', 'paid_at' => now()]);
        } elseif ($paystackStatus === 'success') {
            Log::warning('Paystack verify amount mismatch', [
                'product_order_id' => $productOrder->id,
                'expected' => $expectedAmountInKobo,
                'paid' => $amountPaidInKobo,
            ]);
        }

        return response()->json([
            // refill/attachingRefill let the callback screen say "this rides
            // on your subscription refill" and link straight there.
            'product_order' => $productOrder->fresh(['items', 'refill', 'attachingRefill', 'order', 'attachingOrder']),
            'paystack_status' => $paystackStatus,
        ]);
    }
}
