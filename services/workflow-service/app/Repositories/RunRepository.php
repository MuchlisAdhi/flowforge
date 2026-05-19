<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\WorkflowRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Run query encapsulation. All queries scoped by tenant_id.
 */
class RunRepository
{
    public function findForTenant(string $id, string $tenantId, array $relations = []): ?WorkflowRun
    {
        return WorkflowRun::query()
            ->where('tenant_id', $tenantId)
            ->when($relations, fn (Builder $q) => $q->with($relations))
            ->find($id);
    }

    public function listForTenant(
        string $tenantId,
        ?string $status = null,
        ?string $workflowId = null,
        ?string $from = null,
        ?string $to = null,
        string $sortBy = 'created_at',
        string $sortDir = 'desc',
        int $perPage = 20,
    ): LengthAwarePaginator {
        $allowedSorts = ['created_at', 'started_at', 'completed_at', 'status'];
        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';
        $sortDir = $sortDir === 'asc' ? 'asc' : 'desc';
        $perPage = min(max($perPage, 1), 100);

        return WorkflowRun::query()
            ->where('tenant_id', $tenantId)
            ->with(['workflow:id,name', 'version:id,version'])
            ->when($status, fn (Builder $q) => $q->where('status', $status))
            ->when($workflowId, fn (Builder $q) => $q->where('workflow_id', $workflowId))
            ->when($from, fn (Builder $q) => $q->where('created_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('created_at', '<=', $to))
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage);
    }
}
