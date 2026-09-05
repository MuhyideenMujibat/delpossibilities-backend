<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'price_per_kg',
        'delivery_fee',
        'off_campus_delivery_fee',
        'offer_active',
        'offer_title',
        'offer_message',
        'offer_price_per_kg',
        'broadcast_active',
        'broadcast_message',
        'broadcast_slot1_pickup',
        'broadcast_slot1_delivery',
        'broadcast_slot2_pickup',
        'broadcast_slot2_delivery',
        'loyalty_enabled',
        'loyalty_threshold_kg',
        'loyalty_discount_percent',
        'session_starts_at',
        'session_ends_at',
        'semester_starts_at',
        'semester_ends_at',
        'referral_discount_percent',
        'investment_bank_name',
        'investment_account_name',
        'investment_account_number',
        'investment_monthly_rate_percent',
        'investment_minimum_amount',
        'investment_tenures_months',
        'investment_whatsapp_number',
    ];

    protected function casts(): array
    {
        return [
            'price_per_kg' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'off_campus_delivery_fee' => 'decimal:2',
            'offer_active' => 'boolean',
            'offer_price_per_kg' => 'decimal:2',
            'broadcast_active' => 'boolean',
            'loyalty_enabled' => 'boolean',
            'loyalty_threshold_kg' => 'decimal:2',
            'loyalty_discount_percent' => 'decimal:2',
            'session_starts_at' => 'date',
            'session_ends_at' => 'date',
            'semester_starts_at' => 'date',
            'semester_ends_at' => 'date',
            'referral_discount_percent' => 'decimal:2',
            'investment_monthly_rate_percent' => 'decimal:2',
            'investment_minimum_amount' => 'decimal:2',
        ];
    }

    // Falls back to [6, 12] if ever null (e.g. a settings row created
    // without the migration's backfill) so the calculator always has
    // tenure options to offer instead of an empty list.
    protected function investmentTenuresMonths(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? array_map('intval', json_decode($value, true)) : [6, 12],
            set: fn ($value) => json_encode(array_map('intval', $value ?: [6, 12])),
        );
    }

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }

    // The loyalty program only does anything when it's switched on AND both
    // knobs it needs are set. Every loyalty code path guards on this so a
    // half-configured program (enabled but no threshold, say) is simply inert
    // rather than dividing by zero or unlocking instantly.
    public function loyaltyActive(): bool
    {
        return (bool) $this->loyalty_enabled
            && (float) $this->loyalty_threshold_kg > 0
            && (float) $this->loyalty_discount_percent > 0;
    }

    // Falls back to the referral coupon's built-in default when the admin
    // hasn't set one, so referrals keep working out of the box.
    public function referralDiscountPercent(): float
    {
        return (float) ($this->referral_discount_percent ?? User::REFERRAL_DISCOUNT_PERCENT);
    }
}
