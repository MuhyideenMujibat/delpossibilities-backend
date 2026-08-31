<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubscriberController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
        ]);

        $user = $request->user();
        $plan = SubscriptionPlan::findOrFail($validated['subscription_plan_id']);

        // A student can only ride one subscription at a time — a fresh one
        // can only start once the last is paid-for-and-over (expired) or
        // was never paid (pending, e.g. abandoned checkout gets replaced).
        $existing = $user->subscribers()->where('status', 'active')->first();
        if ($existing) {
            throw ValidationException::withMessages([
                'subscription_plan_id' => ['You already have an active subscription.'],
            ]);
        }

        $pendingExisting = $user->subscribers()->where('status', 'pending')->latest()->first();
        if ($pendingExisting) {
            return response()->json($pendingExisting->load('plan'), 200);
        }

        if (! Subscriber::calendarReady($plan->package_type)) {
            return response()->json([
                'message' => 'Subscriptions are not open yet — please try again shortly.',
            ], 422);
        }

        $subscriber = $user->subscribers()->create([
            'customer_id' => Subscriber::generateCustomerId(),
            'subscription_plan_id' => $plan->id,
            'locked_price' => $plan->price,
            'status' => 'pending',
            'recipient_name' => $user->name,
            'recipient_phone' => $user->phone,
            'recipient_address' => $user->hostel,
        ]);

        return response()->json($subscriber->load('plan'), 201);
    }

    public function mine(Request $request)
    {
        $subscriber = $request->user()->subscribers()
            ->with(['plan', 'refills' => fn ($q) => $q->latest()])
            ->latest()
            ->first();

        // response()->json(null) does NOT produce a JSON `null` body — Symfony's
        // JsonResponse constructor coerces a null payload to an empty
        // \ArrayObject first, so callers with no subscriber would otherwise get
        // back `{}` (truthy) instead of `null`, breaking every frontend check
        // like `data && ...` or `Boolean(data)`.
        if (! $subscriber) {
            return response('null', 200, ['Content-Type' => 'application/json']);
        }

        return response()->json($subscriber);
    }

    public function pay(Request $request, Subscriber $subscriber)
    {
        $user = $request->user();

        if ($subscriber->user_id !== $user->id) {
            abort(403, 'This subscription does not belong to you.');
        }

        if ($subscriber->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['This subscription has already been paid for or handled.'],
            ]);
        }

        if (! Subscriber::calendarReady($subscriber->plan->package_type)) {
            return response()->json([
                'message' => 'Subscriptions are not open yet — please try again shortly.',
            ], 422);
        }

        $pendingPayment = $subscriber->payments()->whereNull('paid_at')->whereNotNull('paystack_reference')->latest()->first();
        if ($pendingPayment && $pendingPayment->payment_authorization_url) {
            return response()->json([
                'authorization_url' => $pendingPayment->payment_authorization_url,
                'reference' => $pendingPayment->paystack_reference,
            ]);
        }

        $reference = 'subscription_'.$subscriber->id.'_'.Str::random(10);

        try {
            $response = Http::withToken(config('services.paystack.secret_key'))
                ->post('https://api.paystack.co/transaction/initialize', [
                    'email' => $user->email,
                    'amount' => (int) ($subscriber->locked_price * 100),
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
                'subscriber_id' => $subscriber->id,
                'response' => $response->json(),
            ]);

            return response()->json([
                'message' => 'Unable to initialize payment. Please try again shortly.',
            ], 500);
        }

        $authorizationUrl = $response->json('data.authorization_url');

        $subscriber->payments()->create([
            'amount' => $subscriber->locked_price,
            'paystack_reference' => $reference,
            'payment_authorization_url' => $authorizationUrl,
        ]);

        return response()->json([
            'authorization_url' => $authorizationUrl,
            'reference' => $reference,
        ]);
    }

    // Synchronous counterpart to the webhook, called right after Paystack
    // redirects back — mirrors OrderController::verifyPayment.
    public function verifyPayment(Request $request, Subscriber $subscriber)
    {
        $user = $request->user();

        if ($subscriber->user_id !== $user->id) {
            abort(403, 'This subscription does not belong to you.');
        }

        if ($subscriber->status !== 'pending') {
            return response()->json(['subscriber' => $subscriber, 'paystack_status' => 'success']);
        }

        $payment = $subscriber->payments()->whereNotNull('paystack_reference')->latest()->first();
        if (! $payment) {
            throw ValidationException::withMessages([
                'status' => ['No payment has been started for this subscription yet.'],
            ]);
        }

        try {
            $response = Http::withToken(config('services.paystack.secret_key'))
                ->get('https://api.paystack.co/transaction/verify/'.$payment->paystack_reference);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Paystack verify connection error: '.$e->getMessage());

            return response()->json([
                'message' => 'Unable to reach the payment provider. Please try again shortly.',
            ], 500);
        }

        if (! $response->successful() || ! $response->json('status')) {
            Log::error('Paystack verify transaction failed', [
                'subscriber_id' => $subscriber->id,
                'response' => $response->json(),
            ]);

            return response()->json([
                'message' => 'Unable to verify this payment right now. Please try again shortly.',
            ], 500);
        }

        $paystackStatus = $response->json('data.status');
        $amountPaidInKobo = $response->json('data.amount');
        $expectedAmountInKobo = (int) ($payment->amount * 100);

        $activationMessage = null;

        if ($paystackStatus === 'success' && $amountPaidInKobo === $expectedAmountInKobo) {
            $subscriber->load('plan');
            try {
                $subscriber->activate($payment);
            } catch (\RuntimeException $e) {
                $activationMessage = 'Your payment was received, but subscriptions are not open yet — please check back shortly.';
            }
        } elseif ($paystackStatus === 'success') {
            Log::warning('Paystack subscription verify amount mismatch', [
                'subscriber_id' => $subscriber->id,
                'expected' => $expectedAmountInKobo,
                'paid' => $amountPaidInKobo,
            ]);
        }

        return response()->json([
            'subscriber' => $subscriber->fresh('plan'),
            'paystack_status' => $paystackStatus,
            'message' => $activationMessage,
        ]);
    }

    // A real ownership handoff, not just a contact-details relabel: the
    // current owner enters the recipient's own existing Customer ID, and
    // this subscriber row's user_id moves to them outright — their
    // remaining kg, refill history and everything else that reads through
    // user_id goes with it. Only offered (frontend) and allowed (here) while
    // there's actually something left to hand over — a subscription with no
    // kg left or past its end date has nothing to transfer.
    public function transfer(Request $request, Subscriber $subscriber)
    {
        $user = $request->user();

        if ($subscriber->user_id !== $user->id) {
            abort(403, 'This subscription does not belong to you.');
        }

        if ($subscriber->status !== 'active'
            || (float) $subscriber->remaining_kg <= 0
            || ($subscriber->ends_at && $subscriber->ends_at->isPast())) {
            throw ValidationException::withMessages([
                'status' => ['This subscription has nothing left to transfer.'],
            ]);
        }

        $validated = $request->validate([
            'customer_id' => ['required', 'string', 'exists:subscribers,customer_id'],
        ]);

        $recipientUserId = Subscriber::where('customer_id', $validated['customer_id'])->value('user_id');

        if ($recipientUserId === $user->id) {
            throw ValidationException::withMessages([
                'customer_id' => ["You can't transfer a subscription to yourself."],
            ]);
        }

        // Mirrors the sender-side check just above (and the frontend's
        // isUsableSubscription in MySubscription.jsx) — a `status='active'`
        // row alone isn't enough, since status never auto-flips to
        // 'expired'. Only block the transfer if the recipient's
        // subscription is genuinely still usable: active, kg remaining, and
        // not past its end date.
        $recipientHasActive = Subscriber::where('user_id', $recipientUserId)
            ->where('status', 'active')
            ->where('remaining_kg', '>', 0)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->exists();
        if ($recipientHasActive) {
            throw ValidationException::withMessages([
                'customer_id' => ['This customer already has an active subscription.'],
            ]);
        }

        $recipient = \App\Models\User::findOrFail($recipientUserId);

        $subscriber->update([
            'user_id' => $recipientUserId,
            'recipient_name' => $recipient->name,
            'recipient_phone' => $recipient->phone,
            'recipient_address' => $recipient->hostel,
        ]);

        return response()->json($subscriber->fresh(['plan', 'user']));
    }

    // Admin: searchable/filterable subscriber list.
    public function index(Request $request)
    {
        $query = Subscriber::with(['user', 'plan']);

        if ($request->filled('package_type')) {
            $query->whereHas('plan', fn ($q) => $q->where('package_type', $request->string('package_type')));
        }

        if ($request->filled('tier')) {
            $query->whereHas('plan', fn ($q) => $q->where('tier', $request->string('tier')));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('expiring_within_days')) {
            $query->where('status', 'active')
                ->whereBetween('ends_at', [now(), now()->addDays((int) $request->input('expiring_within_days'))]);
        }

        if ($request->filled('search')) {
            $term = $request->string('search');
            $query->where(function ($q) use ($term) {
                $q->where('customer_id', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$term}%"));
            });
        }

        return response()->json($query->latest()->get());
    }

    // Admin: active count by tier, revenue by package, expiring soon.
    public function reports()
    {
        $activeByTier = Subscriber::where('status', 'active')
            ->with('plan')
            ->get()
            ->groupBy(fn ($subscriber) => $subscriber->plan->tier)
            ->map->count();

        $revenueByPackage = SubscriptionPayment::whereNotNull('paid_at')
            ->with('subscriber.plan')
            ->get()
            ->groupBy(fn ($payment) => $payment->subscriber->plan->package_type)
            ->map(fn ($payments) => $payments->sum('amount'));

        $expiringSoon = Subscriber::where('status', 'active')
            ->whereBetween('ends_at', [now(), now()->addDays(30)])
            ->with(['user', 'plan'])
            ->orderBy('ends_at')
            ->get();

        return response()->json([
            'active_by_tier' => $activeByTier,
            'revenue_by_package' => $revenueByPackage,
            'expiring_soon' => $expiringSoon,
        ]);
    }
}
