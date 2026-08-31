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
        'referral_credit_balance',
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
            'referral_credit_balance' => 'decimal:2',
        ];
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

    // Called from the two paths that count as a "qualifying purchase" for
    // referral purposes — Order/PaystackWebhookController's paid-order
    // side effects, and Subscriber::activate(). A standalone Eazy Market
    // purchase never calls this. The referral_reward_granted flag (checked
    // here, not "is this their first order") is what makes this safely
    // idempotent regardless of which of the two trigger points fires first.
    public function grantReferralRewardIfEligible(): void
    {
        if (! $this->referred_by_user_id || $this->referral_reward_granted) {
            return;
        }

        $amount = (float) (Setting::current()->referral_reward_amount ?? 0);

        if ($amount <= 0) {
            return;
        }

        static::where('id', $this->referred_by_user_id)->increment('referral_credit_balance', $amount);
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
