<?php

namespace App\Http\Middleware;

use App\Models\User;
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
            return response()->json([
                'message' => 'Authentication required',
            ], 401);
        }

        $payload = $this->jwtService->validateToken($token);

        if (! $payload) {
            return response()->json([
                'message' => 'Invalid or expired token',
            ], 401);
        }

        $user = User::with('tenant')->find($payload->sub);

        if (! $user || ! $user->is_active) {
            return response()->json([
                'message' => 'User not found or deactivated',
            ], 401);
        }

        // Attach user and tenant info to request
        $request->attributes->set('auth_user', $user);
        $request->attributes->set('tenant_id', $payload->tenant_id);
        $request->attributes->set('user_role', $payload->role);

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
