<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures tenant_id is always present in the request context.
 * Acts as a safety net — if somehow a request reaches here without tenant context, reject it.
 */
class TenantIsolationMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant context is required',
            ], 403);
        }

        return $next($request);
    }
}
