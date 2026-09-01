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

        // A student can only ride one USABLE subscription at a time. An
        // 'active' row that's out of kg or past its end date doesn't count
        // (status never auto-flips to 'expired'), so an exhausted subscriber
        // — including someone who received a transfer that had nothing left
        // in it — can still start a fresh one.
        $existing = $user->subscribers()->usable()->exists();
        if ($existing) {
            throw ValidationException::withMessages([
                'subscription_plan_id' => ['You already have an active subscription.'],
            ]);
        }

        // A pending row is an abandoned, still-unpaid pick. If the student is
        // now choosing the SAME plan again, hand it straight back
        // (idempotent). If they picked a DIFFERENT plan, they walked away
        // from the first one — repoint this row to the new plan/price rather
        // than making them pay for the plan they abandoned, and bin any
        // stale unpaid Paystack transaction so pay() starts a fresh one for
        // the right amount.
        $pendingExisting = $user->subscribers()->where('status', 'pending')->latest()->first();
        if ($pendingExisting && (int) $pendingExisting->subscription_plan_id === (int) $plan->id) {
            return response()->json($pendingExisting->load('plan'), 200);
        }

        if (! Subscriber::calendarReady($plan->package_type)) {
            return response()->json([
                'message' => 'Subscriptions are not open yet — please try again shortly.',
            ], 422);
        }

        if ($pendingExisting) {
            $pendingExisting->payments()->whereNull('paid_at')->delete();
            $pendingExisting->update([
                'subscription_plan_id' => $plan->id,
                'locked_price' => $plan->price,
            ]);

            return response()->json($pendingExisting->fresh()->load('plan'), 200);
        }

        $subscriber = $user->subscribers()->create([
            // A subscriber row's customer_id always mirrors whoever
            // currently owns it — this account's own permanent code, set
            // once at signup (see User::customerIdFor) and never changed by
            // subscribing, resubscribing, or receiving a transfer. It only
            // moves when THIS account gives a subscription away (see
            // transfer(), which repoints it to the recipient's own code).
            // subscribers.customer_id has no uniqueness constraint (see
            // migration 2026_09_01_190000) precisely because the same code
            // legitimately reappears on every one of an account's own rows
            // across subscription cycles. generateCustomerId() is only a
            // defensive fallback for the practically-impossible case of a
            // blank account-level code.
            'customer_id' => $user->customer_id ?: Subscriber::generateCustomerId(),
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
        $user = $request->user();

        // Refill history is scoped to who actually requested each one
        // (Refill::user_id, snapshotted at creation), not to whoever
        // currently owns the subscriber row it's attached to — a transfer
        // moves the subscription, not the delivery history riding on it, so
        // a transfer recipient never inherits the sender's past refills (and
        // vice versa for a still-pending one the sender chose to "carry").
        $subscribers = $user->subscribers()
            ->with(['plan', 'refills' => fn ($q) => $q->where('user_id', $user->id)->latest()->with(['productOrder.items', 'attachedProductOrder.items'])])
            ->latest()
            ->get();

        // ->latest() alone returns whichever row was created most recently.
        // After a subscription is transferred IN, that recipient may still
        // have an older stale row of their own — an abandoned 'pending'
        // checkout, or an 'expired' one — that is newer than the handed-over
        // subscription and would hide it. Always surface a still-usable
        // active subscription first.
        //
        // A freshly-created subscription sits at status='pending' until
        // payment is verified (synchronously via verify-payment, or async via
        // the webhook) — it is NOT yet 'active'. If this account also has an
        // older row that's 'active' but exhausted/expired (status never
        // auto-flips, see scopeUsable), that stale row would otherwise win
        // the next tier below and mask the subscription the student just
        // paid for — including on the payment-callback screen, which relies
        // on this endpoint returning "the one being paid for". So a pending
        // row must outrank a merely-active-but-unusable one, and only a
        // truly exhausted/expired active row (no pending in flight) falls
        // through to the last two tiers.
        $subscriber = $subscribers->first(fn ($s) => $s->status === 'active'
                && (float) $s->remaining_kg > 0
                && (is_null($s->ends_at) || $s->ends_at->isFuture()))
            ?? $subscribers->firstWhere('status', 'pending')
            ?? $subscribers->firstWhere('status', 'active')
            ?? $subscribers->first();

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
            // Every account carries a customer_id from signup now, so the
            // recipient can be any user — they do NOT need to have subscribed
            // before (that was the old "invalid id" bug: it only looked in
            // the subscribers table). Legacy subscriber codes still resolve.
            'customer_id' => [
                'required', 'string',
                function ($attribute, $value, $fail) {
                    $known = \App\Models\User::where('customer_id', $value)->exists()
                        || Subscriber::where('customer_id', $value)->exists();

                    if (! $known) {
                        $fail('No account was found with that Customer ID.');
                    }
                },
            ],
            'pending_refill' => ['sometimes', Rule::in(['terminate', 'carry'])],
        ]);

        $recipientUserId = \App\Models\User::where('customer_id', $validated['customer_id'])->value('id')
            ?: Subscriber::where('customer_id', $validated['customer_id'])->value('user_id');

        if ((int) $recipientUserId === (int) $user->id) {
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
        $recipientHasActive = Subscriber::where('user_id', $recipientUserId)->usable()->exists();
        if ($recipientHasActive) {
            throw ValidationException::withMessages([
                'customer_id' => ['This customer already has an active subscription.'],
            ]);
        }

        $recipient = \App\Models\User::findOrFail($recipientUserId);

        // Handle a still-pending refill the sender hasn't received yet.
        $pendingRefill = $subscriber->refills()->where('status', 'pending')->latest()->first();
        if ($pendingRefill) {
            if (($validated['pending_refill'] ?? 'terminate') === 'carry') {
                // Sender keeps it: reserve its kg out of the pool now so it
                // isn't deducted again on delivery, snapshot the sender as
                // the recipient (the subscriber's own recipient_* is about
                // to point at the new owner), and advance it to 'approved'
                // so the admin still fulfils it.
                $subscriber->decrement('remaining_kg', min(
                    (float) ($pendingRefill->kg_requested ?? 0),
                    (float) $subscriber->remaining_kg
                ));

                $pendingRefill->update([
                    'status' => 'approved',
                    'recipient_name' => $user->name,
                    'recipient_phone' => $user->phone,
                    'kg_prereserved' => true,
                ]);
            } else {
                // Detach any cart (bundled or attached) that was only riding
                // on this now-cancelled refill so it isn't stranded on a dead
                // trip. A paid one goes back to 'approved' (re-attachable
                // elsewhere); an unpaid bundle stays 'pending' for the
                // subscriber to deal with.
                foreach ([['product_order_id', $pendingRefill->productOrder], ['attached_product_order_id', $pendingRefill->attachedProductOrder]] as [$column, $cart]) {
                    if (! $cart) {
                        continue;
                    }
                    if ($cart->status !== 'pending') {
                        $cart->update(['status' => 'approved']);
                    }
                    $pendingRefill->update([$column => null]);
                }
                $pendingRefill->update(['status' => 'cancelled']);
            }

            $subscriber->refresh();
        }

        $subscriber->update([
            'user_id' => $recipientUserId,
            // The recipient's OWN permanent code, not the sender's — a
            // transfer moves ownership of the subscription, not the
            // sender's identity. The sender keeps their real code for their
            // next subscription (see store()); refill history stays split
            // by requester regardless (see Refill::user_id).
            'customer_id' => $recipient->customer_id,
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
