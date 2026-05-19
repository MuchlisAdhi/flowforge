<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Encapsulates all workflow query logic.
 * Every query method requires tenant_id — there is no way to accidentally
 * bypass tenant isolation at the repository level.
 */
class WorkflowRepository
{
    public function findForTenant(string $id, string $tenantId, array $relations = []): ?Workflow
    {
        return Workflow::query()
            ->where('tenant_id', $tenantId)
            ->when($relations, fn (Builder $q) => $q->with($relations))
            ->find($id);
    }

    public function findForTenantOrFail(string $id, string $tenantId, array $relations = []): Workflow
    {
        $workflow = $this->findForTenant($id, $tenantId, $relations);

        if (! $workflow) {
            abort(404, 'Workflow not found');
        }

        return $workflow;
    }

    public function listForTenant(
        string $tenantId,
        ?string $search = null,
        ?string $status = null,
        string $sortBy = 'created_at',
        string $sortDir = 'desc',
        int $perPage = 15,
    ): LengthAwarePaginator {
        $allowedSorts = ['name', 'created_at', 'updated_at'];
        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';
        $sortDir = $sortDir === 'asc' ? 'asc' : 'desc';
        $perPage = min(max($perPage, 1), 100);

        return Workflow::query()
            ->where('tenant_id', $tenantId)
            ->with('latestVersion')
            ->when($status !== null, function (Builder $q) use ($status) {
                $q->where('is_active', $status === 'active');
            })
            ->when($search, function (Builder $q) use ($search) {
                $q->where(function (Builder $sub) use ($search) {
                    $sub->where('name', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage);
    }

    public function getLatestVersionNumber(string $workflowId): int
    {
        return (int) WorkflowVersion::where('workflow_id', $workflowId)->max('version');
    }

    public function getVersion(string $workflowId, int $version): ?WorkflowVersion
    {
        return WorkflowVersion::where('workflow_id', $workflowId)
            ->where('version', $version)
            ->first();
    }
}
