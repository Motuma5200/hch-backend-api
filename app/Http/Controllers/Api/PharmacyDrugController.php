<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Hospital;
use App\Models\Drug;
use Illuminate\Support\Facades\Auth;

class PharmacyDrugController extends Controller
{
    // Return assigned hospital for authenticated user
    public function assignedHospital(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($user->hospital_id) {
            $hospital = Hospital::find($user->hospital_id);
            return response()->json(['hospital' => $hospital]);
        }

        return response()->json(null, 204);
    }

    // List drugs for a hospital
    public function index(Hospital $hospital)
    {
        $user = Auth::user();
        if (!$this->userCanAccessHospital($user, $hospital)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $drugs = Drug::where('hospital_id', $hospital->id)->get();
        return response()->json($drugs);
    }

    // Create drug
    public function store(Request $request, Hospital $hospital)
    {
        $user = $request->user();
        if (!$this->userCanAccessHospital($user, $hospital)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'dosage' => 'nullable|string',
        ]);

        $drug = Drug::create(array_merge($data, ['hospital_id' => $hospital->id]));
        return response()->json($drug, 201);
    }

    // Update drug
    public function update(Request $request, Hospital $hospital, Drug $drug)
    {
        $user = $request->user();
        if (!$this->userCanAccessHospital($user, $hospital)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($drug->hospital_id !== $hospital->id) {
            return response()->json(['message' => 'Drug does not belong to hospital'], 400);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'dosage' => 'nullable|string',
        ]);

        $drug->update($data);
        return response()->json($drug);
    }

    // Delete drug
    public function destroy(Request $request, Hospital $hospital, Drug $drug)
    {
        $user = $request->user();
        if (!$this->userCanAccessHospital($user, $hospital)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($drug->hospital_id !== $hospital->id) {
            return response()->json(['message' => 'Drug does not belong to hospital'], 400);
        }

        $drug->delete();
        return response()->json(['message' => 'Deleted']);
    }

    protected function userCanAccessHospital($user, Hospital $hospital)
    {
        if (!$user) return false;
        if ($user->role === 'admin') return true;
        return $user->hospital_id && $user->hospital_id === $hospital->id;
    }
}
