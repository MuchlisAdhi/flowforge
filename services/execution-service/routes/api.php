<?php

use App\Jobs\ExecuteWorkflowJob;
use App\Models\WorkflowRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::post('/executions/run', function (Request $request) {
    $runId = $request->input('run_id');

    if (!$runId) {
        return response()->json(['message' => 'run_id is required'], 422);
    }

    // Dispatch execution job to be processed by the queue worker
    ExecuteWorkflowJob::dispatch($runId);

    return response()->json(['message' => 'Execution dispatched', 'run_id' => $runId]);
});
