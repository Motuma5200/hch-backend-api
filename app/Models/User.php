<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable,HasApiTokens;

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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            // 'password' => 'hashed',   ← comment out or remove this line
        ];
    }

    /**
     * Prevent Laravel from automatically hashing the password attribute.
     * This allows plain-text passwords to be stored directly.
     */
    public function setPasswordAttribute($value)
    {
        // Store exactly what was given (no hashing)
        $this->attributes['password'] = $value;
    }

    /**
     * Tell Laravel's authentication system to use the plain-text value
     * when checking passwords during login (Auth::attempt).
     */
    public function getAuthPassword()
    {
        // Return the raw value stored in the database
        return $this->attributes['password'] ?? null;
    }
}