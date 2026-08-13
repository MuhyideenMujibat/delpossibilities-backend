<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserType extends Model
{
    protected $fillable = [
        'name',
    ];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_user_type');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
