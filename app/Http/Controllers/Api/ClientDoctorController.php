<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ClientDoctorController extends Controller
{
    /**
     * Fetch a list of all verified and approved doctors for public browsing.
     */
    public function index()
    {
        // 1. Fetch only users who are doctors AND approved by the administrator
        $doctors = User::where('role', 'doctor')
            ->where('approved', true)
            ->orderBy('name', 'asc')
            ->get([
                'id', 
                'name', 
                'email', 
                'organisation', 
                'hospital_id', 
                'doctor_profile_json' // 🔴 CRITICAL: Pull this column so React can parse it!
            ]);

        // 2. Return the collection as a clean JSON array structure back to React
        return response()->json($doctors);
    }
}