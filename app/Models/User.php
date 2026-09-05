<?php


namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    // Default percentage knocked off the gas cost of an order when the
    // student has a referral coupon to spend (see referral_discount_available)
    // — used only when the admin hasn't set Setting::referral_discount_percent
    // (see Setting::referralDiscountPercent).
    public const REFERRAL_DISCOUNT_PERCENT = 10;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'user_type_id',
        'hostel',
        'location_type',
        'phone',
        'cylinder_image',
        'email_verified_at',
        'referred_by_user_id',
        'referral_reward_granted',
        'referral_discount_available',
    ];

    protected $appends = [
        'cylinder_image_url',
        'permission_keys',
        'loyalty_reward_available',
        'loyalty_kg_to_next_reward',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'loyalty_progress_kg' => 'decimal:2',
            'referral_reward_granted' => 'boolean',
            'referral_discount_available' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Every account gets a shareable customer/referral code the moment
        // it's created — no longer tied to subscribing (see Subscriber, which
        // now reuses this code instead of minting its own). It's just the
        // account's own id, zero-padded, scoped by signup year:
        // DEL-2026-0042. Set on `created`, not `creating`, because the id
        // isn't assigned until the row is inserted.
        static::created(function (User $user) {
            if (blank($user->customer_id)) {
                $user->forceFill([
                    'customer_id' => static::customerIdFor($user),
                ])->saveQuietly();
            }
        });
    }

    public static function customerIdFor(User $user): string
    {
        $year = $user->created_at?->year ?? now()->year;

        return 'DEL-'.$year.'-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT);
    }

    public function orders()
{
    return $this->hasMany(Order::class);
}

    public function subscribers()
    {
        return $this->hasMany(Subscriber::class);
    }

    public function productOrders()
    {
        return $this->hasMany(ProductOrder::class);
    }

    public function investments()
    {
        return $this->hasMany(Investment::class);
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }

    // Called from the paid-gas-order side effects (OrderController::verify and
    // PaystackWebhookController). Gives the person who referred this student
    // a coupon (percentage set by Setting::referral_discount_percent, see
    // Setting::referralDiscountPercent), but only for a real gas order of
    // 3 kg or more, and only once per referred student (the
    // referral_reward_granted flag on the referred user, checked here, keeps
    // it idempotent across both trigger points and any retries). Subscriptions
    // and Eazy Market orders don't count.
    public function grantReferralRewardIfEligible(float $orderKg = 0): void
    {
        if (! $this->referred_by_user_id || $this->referral_reward_granted) {
            return;
        }

        if ($orderKg < 3) {
            return;
        }

        static::where('id', $this->referred_by_user_id)->increment('referral_discount_available');
        $this->update(['referral_reward_granted' => true]);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function userType()
    {
        return $this->belongsTo(UserType::class);
    }

    // The authoritative, per-person permission grants — copied from the
    // user type's defaults when the account is created, then editable
    // individually from the Add User / edit screens.
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_user');
    }

    protected function cylinderImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->cylinder_image ? Storage::disk('public')->url($this->cylinder_image) : null,
        );
    }

    // True once this student's running kg total (since their last redeemed
    // reward) has reached the admin-configured threshold — the portion of
    // their next order beyond the threshold gets the loyalty discount, and
    // the running total then resets.
    protected function loyaltyRewardAvailable(): Attribute
    {
        return Attribute::make(
            get: function () {
                $setting = Setting::current();

                if (! $setting->loyaltyActive()) {
                    return false;
                }

                return (float) $this->loyalty_progress_kg >= (float) $setting->loyalty_threshold_kg;
            },
        );
    }

    // How many more kg this student needs to buy (across paid orders) before
    // the loyalty discount kicks in. Null when the program is off; 0 once the
    // threshold is reached.
    protected function loyaltyKgToNextReward(): Attribute
    {
        return Attribute::make(
            get: function () {
                $setting = Setting::current();

                if (! $setting->loyaltyActive()) {
                    return null;
                }

                $remaining = (float) $setting->loyalty_threshold_kg - (float) $this->loyalty_progress_kg;

                return $remaining > 0 ? round($remaining, 2) : 0;
            },
        );
    }

    // One bundle of the student's current loyalty standing, for the frontend
    // to show after an order (see OrderController::verifyPayment) without
    // having to also fetch /price. Combines the admin config with this
    // student's own progress.
    public function loyaltySummary(): array
    {
        $setting = Setting::current();
        $active = $setting->loyaltyActive();

        return [
            'enabled' => $active,
            'threshold_kg' => $active ? (float) $setting->loyalty_threshold_kg : null,
            'discount_percent' => $active ? (float) $setting->loyalty_discount_percent : null,
            'progress_kg' => (float) $this->loyalty_progress_kg,
            'reward_available' => $this->loyalty_reward_available,
            'kg_to_next_reward' => $this->loyalty_kg_to_next_reward,
        ];
    }

    // Super admins implicitly hold every permission — they aren't tracked
    // in permission_user, so this is checked separately rather than relying
    // on the pivot table alone. Rides along on every auth response (login,
    // register/verify, GET /user) via $appends, so the frontend never needs
    // a second round trip just to know what an admin can see.
    protected function permissionKeys(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->role === 'super_admin'
                ? Permission::pluck('key')->values()
                : $this->permissions()->pluck('key')->values(),
        );
    }
}
