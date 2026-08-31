<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EazyMarketDeliveryTier extends Model
{
    protected $fillable = [
        'min_amount',
        'max_amount',
        'fee',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
