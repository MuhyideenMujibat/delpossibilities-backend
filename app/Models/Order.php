<?php

namespace App\Models;

use App\Mail\OrderReceiptMail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cylinder_image',
        'kg',
        'price_per_kg',
        'loyalty_discount_applied',
        'loyalty_discount_amount',
        'delivery_fee',
        'total_amount',
        'hostel_address',
        'location_type',
        'status',
        'paystack_reference',
        'payment_authorization_url',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'loyalty_discount_applied' => 'boolean',
            'loyalty_discount_amount' => 'decimal:2',
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
            'body' => "Your order #{$this->total_amount} ({$this->kg} kg) {$message['body']}",
        ]);
    }

    // Best-effort: a failed send (bad SMTP creds, provider hiccup, etc.)
    // shouldn't roll back or fail the payment confirmation that triggered it.
    public function sendReceiptEmail(): void
    {
        if (! $this->user?->email) {
            return;
        }

        try {
            Mail::to($this->user->email)->send(new OrderReceiptMail($this));
        } catch (\Throwable $e) {
            Log::error('Failed to send order receipt email', [
                'order_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }
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

    // Called once this order is confirmed paid. If it redeemed an earned
    // loyalty reward, the student's running total resets so they start
    // building toward the next one; otherwise this order's kg counts
    // toward their next reward.
    public function applyLoyaltyProgress(): void
    {
        $user = $this->user;

        if (! $user) {
            return;
        }

        $user->loyalty_progress_kg = $this->loyalty_discount_applied
            ? 0
            : (float) $user->loyalty_progress_kg + (float) $this->kg;

        $user->save();
    }
}
