<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Refill extends Model
{
    protected $fillable = [
        'subscriber_id',
        'user_id',
        'product_order_id',
        'attached_product_order_id',
        'cylinder_image',
        'recipient_name',
        'recipient_phone',
        'kg_prereserved',
        'requested_at',
        'delivered_at',
        'kg_requested',
        'kg_delivered',
        'delivery_fee',
        'status',
    ];

    protected $appends = [
        'cylinder_image_url',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'delivered_at' => 'datetime',
            'kg_requested' => 'decimal:2',
            'kg_delivered' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'kg_prereserved' => 'boolean',
        ];
    }

    // Snapshotted at request time (see RefillController::store); falls back to
    // the subscriber's current profile photo if this refill predates the
    // cylinder_image column.
    protected function cylinderImageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                $path = $this->cylinder_image ?: $this->subscriber?->user?->cylinder_image;

                if (! $path) {
                    return null;
                }

                /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                $disk = Storage::disk('public');

                return $disk->url($path);
            },
        );
    }

    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class);
    }

    // Snapshotted at request time (RefillController::store) and never
    // touched by a later transfer — this is who actually asked for this
    // refill, independent of who currently owns the subscriber row it's on.
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // The Eazy Market / Gas Services cart this refill's delivery trip is
    // also carrying, if any — checked out and paid for on its own (a
    // refill has no payment step), but tracked/delivered together. This one
    // was built from the refill request itself and paid for right after.
    public function productOrder()
    {
        return $this->belongsTo(ProductOrder::class);
    }

    // A cart that was already paid for standalone and is just tagged onto
    // this delivery for fulfilment — mirrors Order::attachedProductOrder.
    public function attachedProductOrder()
    {
        return $this->belongsTo(ProductOrder::class, 'attached_product_order_id');
    }
}
