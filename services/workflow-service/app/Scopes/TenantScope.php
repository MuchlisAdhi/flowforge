<?php

declare(strict_types=1);

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that automatically applies tenant filtering.
 *
 * Safety net: even if a developer forgets to call ->where('tenant_id', ...),
 * this scope ensures no cross-tenant data leaks in query results.
 *
 * Usage: Apply via TenantAware trait on models that hold tenant_id.
 * The tenant_id is resolved from the current request context.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = request()?->attributes?->get('tenant_id');

        if ($tenantId) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);
        }
    }
}
