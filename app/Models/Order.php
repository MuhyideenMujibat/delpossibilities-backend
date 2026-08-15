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

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    // Mirrors STATUS_LABELS in the frontend's api.js — kept in sync manually
    // since this is the one place status text needs to read naturally in a
    // sentence rather than as a standalone label.
    private const STATUS_MESSAGES = [
        'approved' => ['title' => 'Payment confirmed', 'body' => 'has been approved and is awaiting pickup for refilling.'],
        'picked_up' => ['title' => 'Cylinder picked up', 'body' => 'has been picked up and is being refilled.'],
        'delivered' => ['title' => 'Order delivered', 'body' => 'has been delivered. Enjoy!'],
    ];

    public function notifyStatusChange(): void
    {
        $message = self::STATUS_MESSAGES[$this->status] ?? null;

        if (! $message) {
            return;
        }

        $this->notifications()->create([
            'user_id' => $this->user_id,
            'title' => $message['title'],
            'body' => "Your order #{$this->id} ({$this->kg} kg) {$message['body']}",
        ]);
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
