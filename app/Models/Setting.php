<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'price_per_kg',
    ];

    protected function casts(): array
    {
        return [
            'price_per_kg' => 'decimal:2',
        ];
    }

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }
}
