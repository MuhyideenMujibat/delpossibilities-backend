<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function handle(Request $request)
{
    // Step 1: Get the raw request body exactly as it arrived (not parsed/modified)
    $payload = $request->getContent();

    // Step 2: Get the signature Paystack sent in the header
    $paystackSignature = $request->header('x-paystack-signature');

    // Step 3: Recalculate what the signature SHOULD be, using our secret key
    $expectedSignature = hash_hmac('sha512', $payload, config('services.paystack.secret_key'));

    // Step 4: Compare them. If they don't match, reject immediately.
    if ($paystackSignature !== $expectedSignature) {
        return response()->json(['message' => 'Invalid signature'], 401);
    }

   // Step 5: Decode the JSON payload into a PHP array so we can read its fields
    $event = json_decode($payload, true);

    // Step 6: We only care about successful charge events
    if (($event['event'] ?? null) !== 'charge.success') {
        return response()->json(['status' => 'ignored']);
    }

    // Step 7: Pull out the reference and amount Paystack says was paid
    $reference = $event['data']['reference'];
    $amountPaidInKobo = $event['data']['amount'];

    // Step 8: Find the order using that reference
    $order = \App\Models\Order::where('paystack_reference', $reference)->first();

    if (!$order) {
        // No matching order found — log it, but still respond 200 so Paystack doesn't keep retrying
        return response()->json(['status' => 'order not found']);
    }

    // Step 9: Compare the amount Paystack says was paid against what the order
    // should actually cost. total_amount is stored in naira, so convert to kobo
    // to compare fairly.
    $expectedAmountInKobo = (int) ($order->total_amount * 100);

    if ($amountPaidInKobo !== $expectedAmountInKobo) {
        // Amount mismatch — do NOT approve. Log it so you can investigate manually.
        Log::warning('Paystack webhook amount mismatch', [
            'order_id' => $order->id,
            'expected' => $expectedAmountInKobo,
            'paid' => $amountPaidInKobo,
        ]);

        return response()->json(['status' => 'amount mismatch']);
    }

    // Step 10: Everything checks out — approve the order
    if ($order->status === 'pending') {
        $order->update([
            'status' => 'approved',
            'paid_at' => now(),
        ]);
    }

    return response()->json(['status' => 'success']);
}

}
