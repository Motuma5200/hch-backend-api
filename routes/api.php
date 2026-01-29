<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// You can keep or replace the closure with the controller method
Route::middleware('auth:sanctum')->get('/user', [AuthController::class, 'user']);