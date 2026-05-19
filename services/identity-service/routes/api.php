<?php

use App\Http\Controllers\AuthController;
use App\Http\Middleware\JwtAuthMiddleware;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
});

// Protected routes
Route::middleware(JwtAuthMiddleware::class)->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::get('auth/validate', [AuthController::class, 'validateToken']);
});
