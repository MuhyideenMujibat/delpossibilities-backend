<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = [
        'key',
        'label',
    ];

    public function userTypes()
    {
        return $this->belongsToMany(UserType::class, 'permission_user_type');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'permission_user');
    }
}
