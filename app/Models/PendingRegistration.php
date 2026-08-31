<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingRegistration extends Model
{
    protected $fillable = [
        'name',
        'email',
        'password',
        'hostel',
        'location_type',
        'phone',
        'referred_by_customer_id',
        'otp_code',
        'otp_expires_at',
    ];

    protected $hidden = [
        'password',
        'otp_code',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'otp_code' => 'hashed',
            'otp_expires_at' => 'datetime',
        ];
    }
}
