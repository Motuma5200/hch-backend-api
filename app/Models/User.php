<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\ChatMessage;

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
        'role',
        'organisation',
        'approved',
        'bio',
        'specialization',
        'id_document',
        'hospital_id',
        'doctor_profile_json', // <-- Added to allow database inserts of doctor configuration blocks
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
            'approved' => 'boolean',
            'doctor_profile_json' => 'array', // <-- Automatically parses JSON data to a readable PHP array
        ];
    }

    public function sentMessages()
    {
        return $this->hasMany(ChatMessage::class, 'user_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(ChatMessage::class, 'doctor_id');
    }
}