<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\HospitalController;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;
use App\Http\Controllers\Api\HealthMetricController;
use App\Http\Controllers\Api\SymptomController;
use App\Http\Controllers\Api\PharmacyDrugController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\ClientDoctorController;
use App\Http\Controllers\Api\SecurityController; 

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::match(['get','post','options'],'/sanctum/csrf-cookie', [CsrfCookieController::class, 'show']);

// Symptom endpoints
Route::middleware('auth:sanctum')->post('/health/symptoms/record', [SymptomController::class, 'store']);
Route::post('/health/symptoms/record/test', [SymptomController::class, 'storeTest']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Admin actions
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/admin/users/{id}/approve', [AdminController::class, 'approve']);
    Route::post('/admin/users/{id}/reject', [AdminController::class, 'reject']);
    Route::get('/admin/users/pending', [AdminController::class, 'pending']);
    Route::get('/admin/users', [AdminController::class, 'index']);
    Route::delete('/admin/users/{id}', [AdminController::class, 'destroy']);
    
    // Compatibility Admin Routes
    Route::get('/admin/pending-approvals', [AdminController::class, 'pending']);
    Route::post('/admin/approve/{id}', [AdminController::class, 'approve']);
    Route::post('/admin/reject/{id}', [AdminController::class, 'reject']);
    Route::post('/admin/hospitals', [HospitalController::class, 'store']);
});

// Hospitals
Route::get('/hospitals', [HospitalController::class, 'index']); 
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/hospitals', [HospitalController::class, 'store']);
    Route::put('/hospitals/{id}', [HospitalController::class, 'update']);
    Route::delete('/hospitals/{id}', [HospitalController::class, 'destroy']);
});

// Pharmacy / Drugs
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/pharmacy/assigned-hospital', [PharmacyDrugController::class, 'assignedHospital']);
    Route::match(['get','post','options'],'/admin/assigned-hospital', [PharmacyDrugController::class, 'assignedHospital']);
    Route::get('/hospitals/{hospital}/drugs', [PharmacyDrugController::class, 'index']);
    Route::post('/hospitals/{hospital}/drugs', [PharmacyDrugController::class, 'store']);
    Route::put('/hospitals/{hospital}/drugs/{drug}', [PharmacyDrugController::class, 'update']);
    Route::delete('/hospitals/{hospital}/drugs/{drug}', [PharmacyDrugController::class, 'destroy']);
});

Route::middleware('auth:sanctum')->get('/user', [AuthController::class, 'user']);

// Health metrics endpoints
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/health/metrics/record', [HealthMetricController::class, 'store']);
    Route::get('/health/metrics/status', [HealthMetricController::class, 'getStatus']);
    Route::get('/health/status', [HealthMetricController::class, 'getStatus']);
    Route::get('/health/charts/{metric}', [HealthMetricController::class, 'chart']);
    Route::get('/health/charts/bmi', [HealthMetricController::class, 'chart']);
    Route::get('/health/history', [HealthMetricController::class, 'history']);
});

// No-auth testing fallback endpoints
Route::post('/health/metrics/record/test', [HealthMetricController::class, 'storeTest']);
Route::get('/health/charts/{metric}/test', [HealthMetricController::class, 'chartTest']);
Route::get('/health/charts/bmi/test', [HealthMetricController::class, 'chartTest']);
Route::get('/health/history/test', [HealthMetricController::class, 'historyTest']);
Route::get('/health/status/test', [HealthMetricController::class, 'statusTest']);

// Chat endpoints
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/doctors', [ChatController::class, 'getDoctors']); 
    Route::get('/chat/messages/{doctorId}', [ChatController::class, 'getChatMessages']);
    Route::post('/chat/messages/{doctorId}', [ChatController::class, 'sendChatMessage']);
    Route::post('/chat/doctor/messages/{userId}', [ChatController::class, 'sendDoctorMessage']);
    Route::get('/chat/clients', [ChatController::class, 'getClients']);
});

// Doctor profile & advices
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/doctor/{id}', [DoctorController::class, 'show']);
    Route::put('/doctor/{id}', [DoctorController::class, 'update']);
    Route::get('/doctor/{id}/advices', [DoctorController::class, 'advices']);
    Route::post('/doctor/{id}/advices', [DoctorController::class, 'storeAdvice']);
});

// Dedicated Doctor Directory List for Clients
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/client/doctors', [ClientDoctorController::class, 'index']);
});

// Shared Security Module
Route::middleware('auth:sanctum')->group(function () {
    Route::put('/user/change-password', [SecurityController::class, 'changePassword']);
    
    // CONNECTED: Your permanent account elimination system gateway endpoint
    Route::delete('/user/terminate-account', [AuthController::class, 'destroyAccount']);
});

// Fallback Wildcard Options Interceptor (Keep at bottom)
Route::options('{any}', function () {
    return response()->json([], 200);
})->where('any', '.*');