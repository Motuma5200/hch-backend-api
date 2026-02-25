<?php

namespace App\Http\Controllers\Api;

use App\Models\Hospital;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreHospitalRequest;
use Illuminate\Http\JsonResponse;

class HospitalController extends Controller
{
    // Public: list hospitals
    public function index(Request $request)
    {
        $query = Hospital::query();

        if ($request->has('q')) {
            $q = trim($request->get('q'));
            $query->where('name', 'like', "%{$q}%");
        }

        // Optional: return only hospitals that do NOT already have an approved pharmacy_admin
        if ($request->boolean('available_for_pharmacy_admin')) {
            $query->whereDoesntHave('users', function ($q) {
                $q->where('role', 'pharmacy_admin')->where('approved', true);
            });
        }

        $hospitals = $query->orderBy('name')->get([
            'id', 'name', 'address', 'phone', 'email', 'city', 'state', 'country', 'latitude', 'longitude', 'specialties'
        ]);

        return response()->json($hospitals);
    }

    // Admin: create hospital
    public function store(Request $request)
    {
        $authUser = $request->user();
        if (!$authUser || $authUser->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        // Use Request validation if available
        if ($request instanceof StoreHospitalRequest) {
            $data = $request->validated();
        } else {
            $data = $request->validate([
                'name' => ['required','string','max:255'],
                'address' => ['nullable','string','max:1000'],
                'phone' => ['nullable','string','max:50'],
                'email' => ['nullable','email','max:255'],
                'city' => ['nullable','string','max:120'],
                'state' => ['nullable','string','max:120'],
                'country' => ['nullable','string','max:120'],
                'latitude' => ['nullable','numeric'],
                'longitude' => ['nullable','numeric'],
                'specialties' => ['nullable'],
            ]);
        }

        // Accept comma-separated specialties or array
        if (isset($data['specialties']) && !is_array($data['specialties'])) {
            $data['specialties'] = array_values(array_filter(array_map('trim', explode(',', (string)$data['specialties']))));
        }

        $h = Hospital::create([
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'country' => $data['country'] ?? null,
            'latitude' => isset($data['latitude']) ? (float)$data['latitude'] : null,
            'longitude' => isset($data['longitude']) ? (float)$data['longitude'] : null,
            'specialties' => $data['specialties'] ?? null,
        ]);

        return response()->json(['message' => 'Hospital created','hospital'=>$h], 201);
    }

    /**
     * Update an existing hospital (admin only)
     */
    public function update(Request $request, $id): JsonResponse
    {
        $authUser = $request->user();
        if (!$authUser || $authUser->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $hospital = Hospital::find($id);
        if (!$hospital) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes','required','string','max:255'],
            'address' => ['sometimes','nullable','string','max:1000'],
            'phone' => ['sometimes','nullable','string','max:50'],
            'email' => ['sometimes','nullable','email','max:255'],
            'city' => ['sometimes','nullable','string','max:120'],
            'state' => ['sometimes','nullable','string','max:120'],
            'country' => ['sometimes','nullable','string','max:120'],
            'latitude' => ['sometimes','nullable','numeric'],
            'longitude' => ['sometimes','nullable','numeric'],
            'specialties' => ['sometimes','nullable'],
        ]);

        if (isset($data['specialties']) && !is_array($data['specialties'])) {
            $data['specialties'] = array_values(array_filter(array_map('trim', explode(',', (string)$data['specialties']))));
        }

        $hospital->update($data);

        return response()->json(['message' => 'Hospital updated', 'hospital' => $hospital], 200);
    }

    /**
     * Delete a hospital (admin only)
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $authUser = $request->user();
        if (!$authUser || $authUser->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $hospital = Hospital::find($id);
        if (!$hospital) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $hospital->delete();

        return response()->json(['message' => 'Hospital deleted'], 200);
    }
}
