<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Hospital extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'city',
        'state',
        'country',
        'latitude',
        'longitude',
        'specialties',
    ];

    protected $casts = [
        'specialties' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /**
     * Users related to this hospital (e.g., pharmacy admins)
     */
    public function users()
    {
        return $this->hasMany(User::class, 'hospital_id');
    }
}
