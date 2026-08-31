<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    protected $fillable = [
        'name',
        'fee',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
