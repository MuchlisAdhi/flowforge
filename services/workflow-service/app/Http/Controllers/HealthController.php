<?php

namespace App\Http\Controllers;

use App\Models\WorkflowRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function metrics(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        // Cache metrics for 30 seconds to avoid expensive queries on every request
        $cacheKey = "health_metrics:{$tenantId}";
        $metrics = Cache::remember($cacheKey, 30, function () use ($tenantId) {
            return $this->calculateMetrics($tenantId);
        });

        return response()->json([
            'data' => $metrics,
        ]);
    }

    private function calculateMetrics(string $tenantId): array
    {
        $last24h = now()->subHours(24);

        // Active runs (currently running)
        $activeRuns = WorkflowRun::tenant($tenantId)
            ->where('status', WorkflowRun::STATUS_RUNNING)
            ->count();

        // Runs in last 24 hours
        $recentRuns = WorkflowRun::tenant($tenantId)
            ->where('created_at', '>=', $last24h)
            ->select(
                DB::raw('count(*) as total'),
                DB::raw("count(*) filter (where status = 'success') as success_count"),
                DB::raw("count(*) filter (where status = 'failed') as failed_count"),
                DB::raw("count(*) filter (where status = 'timeout') as timeout_count"),
                DB::raw("avg(extract(epoch from (completed_at - started_at))) filter (where completed_at is not null and started_at is not null) as avg_duration_seconds")
            )
            ->first();

        $total = $recentRuns->total ?? 0;
        $successCount = $recentRuns->success_count ?? 0;
        $failedCount = $recentRuns->failed_count ?? 0;

        return [
            'active_runs' => $activeRuns,
            'last_24h' => [
                'total_runs' => $total,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'timeout_count' => $recentRuns->timeout_count ?? 0,
                'success_rate' => $total > 0 ? round(($successCount / $total) * 100, 2) : 0,
                'failure_rate' => $total > 0 ? round(($failedCount / $total) * 100, 2) : 0,
                'avg_duration_seconds' => round($recentRuns->avg_duration_seconds ?? 0, 2),
            ],
            'calculated_at' => now()->toISOString(),
        ];
    }
}
