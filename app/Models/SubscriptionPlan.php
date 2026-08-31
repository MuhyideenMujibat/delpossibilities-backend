<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'package_type',
        'tier',
        'cylinder_kg',
        'price',
        'foodstuff_pack_value',
        'has_souvenir',
        'has_publicity',
    ];

    protected function casts(): array
    {
        return [
            'cylinder_kg' => 'decimal:2',
            'price' => 'decimal:2',
            'foodstuff_pack_value' => 'decimal:2',
            'has_souvenir' => 'boolean',
            'has_publicity' => 'boolean',
        ];
    }

    public function subscribers()
    {
        return $this->hasMany(Subscriber::class);
    }
}
