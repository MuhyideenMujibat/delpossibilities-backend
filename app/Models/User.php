<?php


namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'user_type_id',
        'hostel',
        'phone',
        'cylinder_image',
        'email_verified_at',
    ];

    protected $appends = [
        'cylinder_image_url',
        'permission_keys',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function orders()
{
    return $this->hasMany(Order::class);
}

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function userType()
    {
        return $this->belongsTo(UserType::class);
    }

    // The authoritative, per-person permission grants — copied from the
    // user type's defaults when the account is created, then editable
    // individually from the Add User / edit screens.
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_user');
    }

    protected function cylinderImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->cylinder_image ? Storage::disk('public')->url($this->cylinder_image) : null,
        );
    }

    // Super admins implicitly hold every permission — they aren't tracked
    // in permission_user, so this is checked separately rather than relying
    // on the pivot table alone. Rides along on every auth response (login,
    // register/verify, GET /user) via $appends, so the frontend never needs
    // a second round trip just to know what an admin can see.
    protected function permissionKeys(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->role === 'super_admin'
                ? Permission::pluck('key')->values()
                : $this->permissions()->pluck('key')->values(),
        );
    }
}
