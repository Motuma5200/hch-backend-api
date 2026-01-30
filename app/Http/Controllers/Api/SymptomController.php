<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\SymptomLog;
use Carbon\Carbon;

class SymptomController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'symptom' => 'required|string|max:255',
            'description' => 'nullable|string',
            'severity' => 'required|string|in:mild,moderate,severe',
            'recorded_at' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = auth()->id();
        if (! $userId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        try {
            $symptom = SymptomLog::create([
                'user_id' => $userId,
                'symptom' => $request->symptom,
                'description' => $request->description ?? null,
                'severity' => $request->severity,
                'recorded_at' => Carbon::parse($request->recorded_at)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Symptom recorded successfully',
                'data' => $symptom
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record symptom',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Test endpoint without auth for local frontend debugging
    public function storeTest(Request $request)
    {
        $userId = $request->header('X-User-Id', 0);

        $validator = Validator::make($request->all(), [
            'symptom' => 'required|string|max:255',
            'description' => 'nullable|string',
            'severity' => 'required|string|in:mild,moderate,severe',
            'recorded_at' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $symptom = SymptomLog::create([
                'user_id' => $userId,
                'symptom' => $request->symptom,
                'description' => $request->description ?? null,
                'severity' => $request->severity,
                'recorded_at' => Carbon::parse($request->recorded_at)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Symptom recorded (test endpoint)',
                'data' => $symptom
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record symptom (test endpoint)',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
