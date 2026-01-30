<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthMetricController;
use App\Http\Controllers\Api\SymptomController;
Route::post('/register', [AuthController::class, 'register']);

// Symptom endpoints
Route::middleware('auth:sanctum')->post('/health/symptoms/record', [SymptomController::class, 'store']);
// Test endpoint (no auth) for local frontend debugging
Route::post('/health/symptoms/record/test', [SymptomController::class, 'storeTest']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

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