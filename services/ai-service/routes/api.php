<?php

use App\Http\Controllers\AIController;
use App\Http\Middleware\JwtAuthMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(JwtAuthMiddleware::class)->prefix('ai')->group(function () {
    Route::post('workflow-builder', [AIController::class, 'workflowBuilder']);
    Route::post('failure-analysis', [AIController::class, 'failureAnalysis']);
});
