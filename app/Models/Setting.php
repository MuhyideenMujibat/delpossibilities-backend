<?php

namespace App\Models;

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
        'loyalty_enabled',
        'loyalty_threshold_kg',
        'loyalty_discount_percent',
    ];

    protected function casts(): array
    {
        return [
            'price_per_kg' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'off_campus_delivery_fee' => 'decimal:2',
            'offer_active' => 'boolean',
            'offer_price_per_kg' => 'decimal:2',
            'loyalty_enabled' => 'boolean',
            'loyalty_threshold_kg' => 'decimal:2',
            'loyalty_discount_percent' => 'decimal:2',
        ];
    }

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }
}
