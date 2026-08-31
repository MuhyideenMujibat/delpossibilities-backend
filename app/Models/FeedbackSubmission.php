<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedbackSubmission extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'rating',
        'message',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
