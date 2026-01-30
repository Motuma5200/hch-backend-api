<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HealthMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'metric_type', 
        'value',
        'unit',
        'additional_data',
        'recorded_at'
    ];

    protected $casts = [
        'additional_data' => 'array',
        'recorded_at' => 'datetime',
        'value' => 'decimal:2'
    ];
}