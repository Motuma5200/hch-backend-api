<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Advice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DoctorController extends Controller
{
    /**
     * Fetch private doctor profile dashboard data.
     */
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
            // FIXED: Removed the dropped 'bio' and 'specialization' columns, added 'doctor_profile_json'
            ->select('id', 'name', 'email', 'organisation', 'hospital_id', 'doctor_profile_json')
            ->first();

        if (! $doctor) {
            return response()->json(['error' => 'Doctor not found'], 404);
        }

        return response()->json($doctor);
    }

    /**
     * Unified update method using a Partial Merge Strategy.
     * Handles Public Profile, Schedule Matrix, and Password Security independently.
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();

        if ($user->role !== 'doctor' || (int) $user->id !== (int) $id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Determine which tab the request is coming from
        $updateType = $request->input('update_type'); 

        // Extract existing JSON column data so we never overwrite anything else
        $currentProfile = $user->doctor_profile_json ?? [];

        // --- TAB 1: PUBLIC PORTAL INFO ---
        if ($updateType === 'professional') {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'specialization' => 'required|string|max:255',
                'bio' => 'nullable|string|max:500',
                'languages' => 'required|array',
                'insuranceProviders' => 'nullable|array',
                'profileImage' => 'nullable|string' // base64 string from React
            ]);

            // Save root column field update
            $user->name = $validated['name'];

            // Merge ONLY professional updates inside the JSON block
            $currentProfile['specialization'] = $validated['specialization'];
            $currentProfile['bio'] = $validated['bio'] ?? '';
            $currentProfile['languages'] = $validated['languages'];
            $currentProfile['insuranceProviders'] = $validated['insuranceProviders'] ?? [];
            
            if (!empty($validated['profileImage'])) {
                $currentProfile['profileImage'] = $validated['profileImage'];
            }
        }

        // --- TAB 2: CONSULTATION HOURS & AVAILABILITY GRID ---
        elseif ($updateType === 'schedule') {
            $validated = $request->validate([
                'scheduleGrid' => 'required|array',
                'scheduleGrid.days' => 'required|array',
                'scheduleGrid.morningStart' => 'required|string',
                'scheduleGrid.morningEnd' => 'required|string',
                'scheduleGrid.afternoonStart' => 'required|string',
                'scheduleGrid.afternoonEnd' => 'required|string',
                'scheduleGrid.videoFee' => 'required|numeric|min:0',
                'scheduleGrid.bufferMinutes' => 'required|string'
            ]);

            // Merge ONLY schedule updates, leaving public info completely untouched
            $currentProfile['scheduleGrid'] = $validated['scheduleGrid'];
        }

        // --- TAB 3: ACCOUNT PASSWORD RESET ---
        elseif ($updateType === 'security') {
            $validated = $request->validate([
                'currentPassword' => 'required|string',
                'newPassword' => 'required|string|min:8',
            ]);

            if (!Hash::check($validated['currentPassword'], $user->password)) {
                return response()->json(['errors' => ['currentPassword' => ['The provided current password does not match your account.']]], 422);
            }

            $user->password = Hash::make($validated['newPassword']);
            $user->save();

            return response()->json(['message' => 'Password security credentials updated successfully.'], 200);
        }
        else {
            return response()->json(['error' => 'Invalid update context action.'], 400);
        }

        // Persist the combined JSON configurations block data map
        $user->doctor_profile_json = $currentProfile;
        $user->save();

        return response()->json([
            'message' => 'Profile updates saved successfully!',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'doctor_profile_json' => $user->doctor_profile_json
            ]
        ], 200);
    }

    /**
     * Fetch all advice notes posted by this doctor.
     */
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

    /**
     * Store a new medical advice post.
     */
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