<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Advice;
use App\Models\User;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    public function show($id)
    {
        $user = auth()->user();

        // Only doctors can fetch doctor profiles
        if ($user->role !== 'doctor') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ((int) $user->id !== (int) $id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $doctor = User::where('id', $id)
            ->where('role', 'doctor')
            ->where('approved', true)
            ->select('id', 'name', 'email', 'organisation', 'hospital_id', 'bio', 'specialization')
            ->first();

        if (! $doctor) {
            return response()->json(['error' => 'Doctor not found'], 404);
        }

        return response()->json($doctor);
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();

        if ($user->role !== 'doctor' || (int) $user->id !== (int) $id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'bio' => 'sometimes|nullable|string|max:2000',
            'specialization' => 'sometimes|nullable|string|max:255',
        ]);

        $user->fill($data);
        $user->save();

        return response()->json($user);
    }

    public function advices($doctorId)
    {
        $user = auth()->user();

        if ($user->role !== 'doctor' || (int) $user->id !== (int) $doctorId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $advices = Advice::where('doctor_id', $doctorId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($advices);
    }

    public function storeAdvice(Request $request, $doctorId)
    {
        $user = auth()->user();

        if ($user->role !== 'doctor' || (int) $user->id !== (int) $doctorId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'text' => 'required|string|max:2000',
        ]);

        $advice = Advice::create([
            'doctor_id' => $doctorId,
            'text' => $data['text'],
        ]);

        return response()->json($advice, 201);
    }
}
