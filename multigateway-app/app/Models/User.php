<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
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

    // Relation with roles
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

     // Verifies if the user has a specified role
     public function hasRole($roleName)
     {
         return $this->roles()->where('name', $roleName)->exists();
     }

     // Verifies if the user has any of the specified roles
    public function hasAnyRole($roleNames)
    {
        return $this->roles()->whereIn('name', (array) $roleNames)->exists();
    }
    //verifies if the user has all the specified roles
    public function hasAllRoles($roleNames)
    {
        $roleNames = (array) $roleNames;
        return $this->roles()->whereIn('name', $roleNames)->count() === count($roleNames);
    }
}
