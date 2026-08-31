<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refill extends Model
{
    protected $fillable = [
        'subscriber_id',
        'product_order_id',
        'requested_at',
        'delivered_at',
        'kg_requested',
        'kg_delivered',
        'delivery_fee',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'delivered_at' => 'datetime',
            'kg_requested' => 'decimal:2',
            'kg_delivered' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
        ];
    }

    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class);
    }

    // The Eazy Market / Gas Services cart this refill's delivery trip is
    // also carrying, if any — checked out and paid for on its own (a
    // refill has no payment step), but tracked/delivered together.
    public function productOrder()
    {
        return $this->belongsTo(ProductOrder::class);
    }
}
