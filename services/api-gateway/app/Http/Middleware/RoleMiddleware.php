<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $userRole = $request->attributes->get('user_role');

        if (! $userRole || ! in_array($userRole, $roles)) {
            return response()->json([
                'message' => 'Insufficient permissions',
            ], 403);
        }

        return $next($request);
    }
}
