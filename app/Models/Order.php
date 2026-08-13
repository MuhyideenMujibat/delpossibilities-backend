<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cylinder_image',
        'kg',
        'price_per_kg',
        'delivery_fee',
        'total_amount',
        'hostel_address',
        'status',
        'paystack_reference',
        'payment_authorization_url',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
    }

    protected $appends = [
        'cylinder_image_url',
    ];

    // An order belongs to one student (user)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected function cylinderImageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->cylinder_image) {
                    return null;
                }

                /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                $disk = Storage::disk('public');

                return $disk->url($this->cylinder_image);
            },
        );
    }
}
