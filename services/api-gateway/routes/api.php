<?php

use App\Http\Controllers\GatewayController;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\RateLimitMiddleware;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

// Apply rate limiting to all API routes
Route::middleware(RateLimitMiddleware::class)->group(function () {

    // Public routes (no auth required)
    Route::post('auth/login', [GatewayController::class, 'login']);
    Route::post('auth/register', [GatewayController::class, 'register']);

    // Authenticated routes
    Route::middleware(JwtAuthMiddleware::class)->group(function () {
        
        // Auth
        Route::get('auth/me', [GatewayController::class, 'me']);

        // Workflows - read (all roles)
        Route::get('workflows', [GatewayController::class, 'workflowIndex']);
        Route::get('workflows/{id}', [GatewayController::class, 'workflowShow']);

        // Workflows - write (admin, editor)
        Route::middleware(RoleMiddleware::class . ':admin,editor')->group(function () {
            Route::post('workflows', [GatewayController::class, 'workflowStore']);
            Route::put('workflows/{id}', [GatewayController::class, 'workflowUpdate']);
            Route::delete('workflows/{id}', [GatewayController::class, 'workflowDestroy']);
            Route::post('workflows/{id}/versions/{version}/rollback', [GatewayController::class, 'workflowRollback']);
            Route::post('workflows/{id}/trigger', [GatewayController::class, 'workflowTrigger']);
        });

        // Runs - read (all roles)
        Route::get('runs', [GatewayController::class, 'runIndex']);
        Route::get('runs/{id}', [GatewayController::class, 'runShow']);

        // Health metrics
        Route::get('health/metrics', [GatewayController::class, 'healthMetrics']);

        // AI features (admin, editor)
        Route::middleware(RoleMiddleware::class . ':admin,editor')->group(function () {
            Route::post('ai/workflow-builder', [GatewayController::class, 'aiWorkflowBuilder']);
            Route::post('ai/failure-analysis', [GatewayController::class, 'aiFailureAnalysis']);
        });

        // SSE stream
        Route::get('sse/executions', [GatewayController::class, 'sseStream']);
    });
});
