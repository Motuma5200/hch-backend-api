<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\HospitalController;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;
use App\Http\Controllers\Api\HealthMetricController;
use App\Http\Controllers\Api\SymptomController;
use App\Http\Controllers\Api\PharmacyDrugController;
Route::post('/register', [AuthController::class, 'register']);

// Symptom endpoints
Route::middleware('auth:sanctum')->post('/health/symptoms/record', [SymptomController::class, 'store']);
// Test endpoint (no auth) for local frontend debugging
Route::post('/health/symptoms/record/test', [SymptomController::class, 'storeTest']);
Route::post('/login', [AuthController::class, 'login']);
// Some frontends call POST for the CSRF cookie; accept GET, POST and OPTIONS here
Route::match(['get','post','options'],'/sanctum/csrf-cookie', [CsrfCookieController::class, 'show']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Admin actions
Route::middleware('auth:sanctum')->post('/admin/users/{id}/approve', [AdminController::class, 'approve']);
Route::middleware('auth:sanctum')->post('/admin/users/{id}/reject', [AdminController::class, 'reject']);
Route::middleware('auth:sanctum')->get('/admin/users/pending', [AdminController::class, 'pending']);
Route::middleware('auth:sanctum')->get('/admin/users', [AdminController::class, 'index']);
// Delete user (admin only)
Route::middleware('auth:sanctum')->delete('/admin/users/{id}', [AdminController::class, 'destroy']);

// Compatibility: older frontend expects `/admin/pending-approvals`
Route::middleware('auth:sanctum')->get('/admin/pending-approvals', [AdminController::class, 'pending']);
// Frontend compatibility aliases
Route::middleware('auth:sanctum')->post('/admin/approve/{id}', [AdminController::class, 'approve']);
Route::middleware('auth:sanctum')->post('/admin/reject/{id}', [AdminController::class, 'reject']);

// Hospitals
Route::get('/hospitals', [HospitalController::class, 'index']);
// Allow POST to /api/hospitals for frontends that post to that route (requires auth)
Route::middleware('auth:sanctum')->post('/hospitals', [HospitalController::class, 'store']);
// Backwards-compatible admin-specific route
Route::middleware('auth:sanctum')->post('/admin/hospitals', [HospitalController::class, 'store']);
// Update and delete hospital
Route::middleware('auth:sanctum')->put('/hospitals/{id}', [HospitalController::class, 'update']);
Route::middleware('auth:sanctum')->delete('/hospitals/{id}', [HospitalController::class, 'destroy']);

// Pharmacy / Drugs for hospital-scoped pharmacy admin
Route::middleware('auth:sanctum')->get('/pharmacy/assigned-hospital', [PharmacyDrugController::class, 'assignedHospital']);
// Compatibility alias used by some frontends
Route::middleware('auth:sanctum')->match(['get','post','options'],'/admin/assigned-hospital', [PharmacyDrugController::class, 'assignedHospital']);
Route::middleware('auth:sanctum')->get('/hospitals/{hospital}/drugs', [PharmacyDrugController::class, 'index']);
Route::middleware('auth:sanctum')->post('/hospitals/{hospital}/drugs', [PharmacyDrugController::class, 'store']);
Route::middleware('auth:sanctum')->put('/hospitals/{hospital}/drugs/{drug}', [PharmacyDrugController::class, 'update']);
Route::middleware('auth:sanctum')->delete('/hospitals/{hospital}/drugs/{drug}', [PharmacyDrugController::class, 'destroy']);

// You can keep or replace the closure with the controller method
Route::middleware('auth:sanctum')->get('/user', [AuthController::class, 'user']);

// Health metrics endpoints
Route::middleware('auth:sanctum')->post('/health/metrics/record', [HealthMetricController::class, 'store']);
Route::middleware('auth:sanctum')->get('/health/metrics/status', [HealthMetricController::class, 'getStatus']);
// Alias used by frontend
Route::middleware('auth:sanctum')->get('/health/status', [HealthMetricController::class, 'getStatus']);

// Chart endpoints (returns series for front-end charts)
Route::middleware('auth:sanctum')->get('/health/charts/{metric}', [HealthMetricController::class, 'chart']);
// explicit BMI route for older clients
Route::middleware('auth:sanctum')->get('/health/charts/bmi', [HealthMetricController::class, 'chart']);

// Temporary test route (no auth) — useful for local frontend debugging
Route::post('/health/metrics/record/test', [HealthMetricController::class, 'storeTest']);
// Temporary test chart endpoints (no auth)
Route::get('/health/charts/{metric}/test', [HealthMetricController::class, 'chartTest']);
Route::get('/health/charts/bmi/test', [HealthMetricController::class, 'chartTest']);
// History endpoints
Route::middleware('auth:sanctum')->get('/health/history', [HealthMetricController::class, 'history']);
Route::get('/health/history/test', [HealthMetricController::class, 'historyTest']);
// Temporary test status endpoint (no auth)
Route::get('/health/status/test', [HealthMetricController::class, 'statusTest']);

// Respond to preflight OPTIONS requests for API routes to avoid 405 Method Not Allowed
Route::options('{any}', function () {
	return response()->json([], 200);
})->where('any', '.*');


Route::get('/hospitals', [HospitalController::class, 'index']);