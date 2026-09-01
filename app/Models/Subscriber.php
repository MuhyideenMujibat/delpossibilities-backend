<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    protected $fillable = [
        'customer_id',
        'user_id',
        'subscription_plan_id',
        'locked_price',
        'status',
        'starts_at',
        'ends_at',
        'remaining_kg',
        'recipient_name',
        'recipient_phone',
        'recipient_address',
    ];

    protected function casts(): array
    {
        return [
            'locked_price' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'remaining_kg' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function refills()
    {
        return $this->hasMany(Refill::class);
    }

    public function payments()
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    // "Usable" = the one row that should actually drive the app: active,
    // still has kg, and not past its end date. `status` never auto-flips to
    // 'expired' (see the comments in SubscriberController), so a plain
    // where('status','active') can hand back a long-dead row — and an
    // account can legitimately hold more than one 'active' row at once
    // (renewed after exhaustion, or received a transfer while still holding
    // an old exhausted one). Every "which subscription is this account
    // on?" lookup should go through here.
    public function scopeUsable($query)
    {
        return $query->where('status', 'active')
            ->where('remaining_kg', '>', 0)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    // Year-scoped, human-friendly, transferable customer ID (DEL-2026-0001).
    // Scoped per calendar year so the sequence — and the id's length — stays
    // short instead of growing unbounded over the platform's lifetime.
    public static function generateCustomerId(): string
    {
        $year = now()->year;
        $sequence = static::where('customer_id', 'like', "DEL-{$year}-%")->count() + 1;

        // The count is only a starting guess — codes can be sparse (accounts
        // that never subscribed, or rows whose code was transferred in from
        // another year). Step forward until we hit one that's actually free
        // so this can never return a value that violates the unique index.
        do {
            $candidate = sprintf('DEL-%d-%04d', $year, $sequence);
            $sequence++;
        } while (static::where('customer_id', $candidate)->exists());

        return $candidate;
    }

    // Shared by both confirmation paths (SubscriberController::verifyPayment
    // and PaystackWebhookController) so the activation logic — flipping
    // status and computing the locked calendar window — lives in one place.
    // Throws if the admin hasn't set the calendar for this package type yet,
    // so a subscriber is never activated with null starts_at/ends_at.
    public function activate(SubscriptionPayment $payment): void
    {
        if (! static::calendarReady($this->plan->package_type)) {
            throw new \RuntimeException('Subscription calendar dates are not set for this package type.');
        }

        $calendar = static::calendarFor($this->plan->package_type);

        $this->update([
            'status' => 'active',
            'starts_at' => $calendar['starts_at'],
            'ends_at' => $calendar['ends_at'],
            // The subscription's whole kg allotment, drawn down by each
            // delivered refill — this, not the calendar window, is what
            // actually gates whether a subscriber can request another
            // refill (see RefillController::store).
            'remaining_kg' => $this->plan->cylinder_kg,
        ]);

        $payment->update(['paid_at' => now()]);
    }

    // The current session/semester calendar (admin-configured on Settings)
    // decides ends_at — every subscriber who joins within the same window
    // shares the same end date, matching how a real academic
    // session/semester works rather than a rolling per-subscriber duration.
    public static function calendarFor(string $packageType): array
    {
        $setting = Setting::current();

        return $packageType === 'session'
            ? ['starts_at' => $setting->session_starts_at, 'ends_at' => $setting->session_ends_at]
            : ['starts_at' => $setting->semester_starts_at, 'ends_at' => $setting->semester_ends_at];
    }

    // Gate for anything that leads to activation (creating a pending
    // subscriber, starting payment, and activation itself) so a student can
    // never end up mid-flow for a package type the admin hasn't scheduled.
    public static function calendarReady(string $packageType): bool
    {
        $calendar = static::calendarFor($packageType);

        return $calendar['starts_at'] && $calendar['ends_at'];
    }
}
