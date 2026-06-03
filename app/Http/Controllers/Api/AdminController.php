<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdminController extends Controller
{
    /**
     * Approve a pending user (admin only)
     * Handles standard users as well as specialized doctor onboarding JSON profiles.
     */
    public function approve(Request $request, $id)
    {
        $authUser = $request->user();

        if (!$authUser || $authUser->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // 1. Process the approval status flag explicitly sent from the client dashboard
        $user->approved = $request->input('approved', true);

        // 2. Catch the doctor profile metrics payload block if attached
        if ($request->has('doctor_profile_json')) {
            // Validate structural fields safely without breaking on complex type configurations
            $request->validate([
                'doctor_profile_json' => 'required|array',
                'doctor_profile_json.specialization' => 'required|string',
            ]);

            // Extract the payload to sanitize integers and floats safely
            $profileData = $request->input('doctor_profile_json');
            
            // Clean up numbers to prevent validation/DB drops if the frontend sent NaN or empty inputs
            $profileData['experienceYears'] = isset($profileData['experienceYears']) ? (int)$profileData['experienceYears'] : 0;
            $profileData['videoFee'] = isset($profileData['videoFee']) ? (float)$profileData['videoFee'] : 0.00;

            // Commit the sanitized array back to the JSON column block
            $user->doctor_profile_json = $profileData;
            
            // ✅ SAFETY CHECK: Map to flat columns ONLY if they actually exist in your database schema layout
            if (Schema::hasColumn('users', 'specialization')) {
                $user->specialization = $profileData['specialization'] ?? 'General Practitioner';
            }
            if (Schema::hasColumn('users', 'bio')) {
                $user->bio = $profileData['bio'] ?? 'Welcome to the health platform!';
            }
        }

        $user->save();

        return response()->json([
            'message' => 'User approved and profile deployed successfully.',
            'user' => $user->only('id', 'name', 'email', 'role', 'organisation', 'approved', 'doctor_profile_json'),
        ]);
    }

    /**
     * Reject (delete) a pending user
     */
    public function reject(Request $request, $id)
    {
        $authUser = $request->user();

        if (!$authUser || $authUser->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Only allow rejecting non-approved accounts
        if ($user->approved) {
            return response()->json(['message' => 'Cannot reject an already approved user'], 400);
        }

        $user->delete();

        return response()->json(['message' => 'User rejected and deleted']);
    }

    /**
     * List pending approvals
     */
    public function pending(Request $request)
    {
        $authUser = $request->user();
        if (!$authUser || $authUser->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $pending = User::where('approved', false)
            ->whereIn('role', ['doctor', 'pharmacy_admin'])
            ->get()
            ->map(function ($u) {
                $hospital = null;
                if ($u->role === 'pharmacy_admin' && $u->hospital_id) {
                    $h = \App\Models\Hospital::find($u->hospital_id);
                    if ($h) $hospital = ['id' => $h->id, 'name' => $h->name, 'city' => $h->city ?? null];
                }

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role,
                    'organisation' => $u->organisation,
                    'hospital' => $hospital,
                    'id_document' => $u->id_document,
                    'id_document_url' => $u->id_document ? asset('storage/' . $u->id_document) : null,
                ];
            });

        return response()->json($pending);
    }

    /**
     * List all users for admin
     */
    public function index(Request $request)
    {
        $authUser = $request->user();
        if (!$authUser || $authUser->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $users = User::orderBy('id', 'desc')->get(['id', 'name', 'email', 'role', 'approved', 'organisation', 'doctor_profile_json']);
        return response()->json($users);
    }

    /**
     * Delete a user (admin only)
     */
    public function destroy(Request $request, $id)
    {
        $authUser = $request->user();

        if (!$authUser || $authUser->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Prevent admin deleting themselves
        if ($authUser->id === $user->id) {
            return response()->json(['message' => 'Cannot delete yourself'], 400);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted']);
    }
}