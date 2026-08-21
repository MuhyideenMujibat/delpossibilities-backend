<?php

namespace App\Http\Controllers;

use App\Models\Order;
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
        $deliveryFee = $validated['location_type'] === 'off_campus'
            ? ($setting->off_campus_delivery_fee ?? 0)
            : ($setting->delivery_fee ?? 0);

        // A student's running kg total (Order::applyLoyaltyProgress) reaching
        // the admin's threshold unlocks a discount. If this order's kg carries
        // them past the threshold mid-order (e.g. 8kg progress + 5kg refill on
        // a 10kg threshold), only the kg beyond what was needed to complete
        // the coupon gets discounted (2kg completes it, the other 3kg is
        // discounted) — not the whole order, and not nothing. price_per_kg
        // stays the normal/offer rate; the discount is its own line item
        // subtracted from the total, so "gas cost" on receipts always reads
        // honestly.
        $loyaltyDiscountApplied = false;
        $loyaltyDiscountAmount = null;

        if ($setting->loyalty_enabled && $setting->loyalty_threshold_kg && $setting->loyalty_discount_percent) {
            $neededToComplete = max((float) $setting->loyalty_threshold_kg - (float) $user->loyalty_progress_kg, 0);
            $discountableKg = max((float) $validated['kg'] - $neededToComplete, 0);

            if ($discountableKg > 0) {
                $loyaltyDiscountAmount = round($pricePerKg * $discountableKg * ($setting->loyalty_discount_percent / 100), 2);
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
            'total_amount' => round($validated['kg'] * $pricePerKg - ($loyaltyDiscountAmount ?? 0) + $deliveryFee, 2),
            'hostel_address' => $validated['hostel_address'],
            'location_type' => $validated['location_type'],
            'status' => 'pending',
        ]);

        return response()->json($order, 201);
    }

    public function myOrders(Request $request)
    {
        return response()->json(
            $request->user()->orders()->latest()->get()
        );
    }

    public function index()
    {
        return response()->json(
            Order::with('user')->latest()->get()
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
            return response()->json(['order' => $order, 'paystack_status' => 'success']);
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
        } elseif ($paystackStatus === 'success') {
            // Paid, but not the right amount — don't silently approve it.
            Log::warning('Paystack verify amount mismatch', [
                'order_id' => $order->id,
                'expected' => $expectedAmountInKobo,
                'paid' => $amountPaidInKobo,
            ]);
        }

        return response()->json([
            'order' => $order->fresh(),
            'paystack_status' => $paystackStatus,
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

        return response()->json($order);
    }
}
