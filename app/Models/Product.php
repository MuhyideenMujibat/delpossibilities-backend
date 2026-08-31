<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    public const GROUPS = ['gas_services', 'eazy_market'];

    public const CATEGORIES = [
        'gas_services' => ['cylinder_sales', 'accessories_burners', 'repair_maintenance', 'repainting', 'cylinder_cleaning'],
        'eazy_market' => ['groceries', 'fresh_produce', 'frozen_foods', 'market_errands', 'peanuts'],
    ];

    protected $fillable = [
        'group',
        'category',
        'name',
        'description',
        'image_path',
        'price',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected $appends = [
        'image_url',
    ];

    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->image_path) {
                    return null;
                }

                /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                $disk = Storage::disk('public');

                return $disk->url($this->image_path);
            },
        );
    }
}
