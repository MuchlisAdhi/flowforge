<?php

declare(strict_types=1);

namespace App\Traits;

use App\Scopes\TenantScope;

/**
 * Trait that automatically scopes all queries by tenant_id.
 *
 * Any model using this trait will NEVER return rows from other tenants,
 * regardless of how the query is constructed. This is a defense-in-depth
 * measure that complements explicit tenant scoping in repositories.
 *
 * To bypass (e.g., in admin/system contexts):
 *   Model::withoutGlobalScope(TenantScope::class)->get();
 */
trait TenantAware
{
    public static function bootTenantAware(): void
    {
        static::addGlobalScope(new TenantScope());

        // Automatically set tenant_id on creation
        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $tenantId = request()?->attributes?->get('tenant_id');
                if ($tenantId) {
                    $model->tenant_id = $tenantId;
                }
            }
        });
    }
}
