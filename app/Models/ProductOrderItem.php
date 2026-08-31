<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOrderItem extends Model
{
    protected $fillable = [
        'product_order_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'group',
        'category',
        'variant_label',
        'unit_price',
        'quantity',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function productOrder()
    {
        return $this->belongsTo(ProductOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
