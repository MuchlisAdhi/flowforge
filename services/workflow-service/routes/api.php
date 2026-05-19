<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\RunController;
use App\Http\Controllers\WorkflowController;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\TenantIsolationMiddleware;
use Illuminate\Support\Facades\Route;

// All routes require authentication and tenant context
Route::middleware([JwtAuthMiddleware::class, TenantIsolationMiddleware::class])->group(function () {

    // Workflows - read access for all roles
    Route::get('workflows', [WorkflowController::class, 'index']);
    Route::get('workflows/{id}', [WorkflowController::class, 'show']);

    // Workflows - write access (admin, editor)
    Route::middleware(RoleMiddleware::class . ':admin,editor')->group(function () {
        Route::post('workflows', [WorkflowController::class, 'store']);
        Route::put('workflows/{id}', [WorkflowController::class, 'update']);
        Route::delete('workflows/{id}', [WorkflowController::class, 'destroy']);
        Route::post('workflows/{id}/versions/{version}/rollback', [WorkflowController::class, 'rollback']);
        Route::post('workflows/{id}/trigger', [WorkflowController::class, 'trigger']);
    });

    // Runs - read access for all roles
    Route::get('runs', [RunController::class, 'index']);
    Route::get('runs/{id}', [RunController::class, 'show']);

    // Health metrics - read access for all roles
    Route::get('health/metrics', [HealthController::class, 'metrics']);
});
