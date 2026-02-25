<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Hospital;

class Drug extends Model
{
    use HasFactory;

    protected $fillable = [
        'hospital_id',
        'name',
        'dosage',
    ];

    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }
}
