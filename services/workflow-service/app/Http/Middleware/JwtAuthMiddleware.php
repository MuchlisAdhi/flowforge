<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthMiddleware
{
    public function __construct(
        private readonly JwtService $jwtService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if (! $token) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        $payload = $this->jwtService->validateToken($token);

        if (! $payload) {
            return response()->json(['message' => 'Invalid or expired token'], 401);
        }

        // Attach auth context from JWT claims (no DB lookup needed in downstream services)
        $request->attributes->set('tenant_id', $payload->tenant_id);
        $request->attributes->set('user_role', $payload->role);
        $request->attributes->set('auth_user', (object) [
            'id' => $payload->sub,
            'tenant_id' => $payload->tenant_id,
            'role' => $payload->role,
            'email' => $payload->email,
        ]);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }
}
