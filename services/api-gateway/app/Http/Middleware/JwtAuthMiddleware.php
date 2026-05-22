<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if (! $token) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        try {
            $payload = JWT::decode($token, new Key(config('jwt.secret'), 'HS256'));
        } catch (\Throwable) {
            return response()->json(['message' => 'Invalid or expired token'], 401);
        }

        $request->attributes->set('tenant_id', $payload->tenant_id);
        $request->attributes->set('user_role', $payload->role);
        $request->attributes->set('auth_user', (object) [
            'id' => $payload->sub,
            'tenant_id' => $payload->tenant_id,
            'role' => $payload->role,
        ]);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        // Try Authorization header first
        $header = $request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        // Fall back to query parameter (needed for SSE/EventSource which can't set headers)
        return $request->query('token');
    }
}
