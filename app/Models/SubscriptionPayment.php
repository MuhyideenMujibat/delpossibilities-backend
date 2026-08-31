<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPayment extends Model
{
    protected $fillable = [
        'subscriber_id',
        'amount',
        'paystack_reference',
        'payment_authorization_url',
        'method',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class);
    }
}
