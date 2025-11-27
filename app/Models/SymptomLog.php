<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SymptomLog extends Model
{
    protected $fillable = [
        'user_id', 'symptom', 'description', 'severity', 'recorded_at'
    ];

    protected $casts = [
        'recorded_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}