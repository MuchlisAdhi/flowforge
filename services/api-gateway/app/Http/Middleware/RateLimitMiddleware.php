<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redis-based sliding window rate limiter.
 */
class RateLimitMiddleware
{
    private int $maxRequests;
    private int $windowSeconds;

    public function __construct()
    {
        $this->maxRequests = (int) config('gateway.rate_limit_per_minute', 60);
        $this->windowSeconds = 60;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->resolveKey($request);
        $currentCount = $this->incrementCounter($key);

        if ($currentCount > $this->maxRequests) {
            return response()->json([
                'message' => 'Too many requests',
                'retry_after' => $this->windowSeconds,
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $this->maxRequests,
                'X-RateLimit-Remaining' => 0,
                'Retry-After' => $this->windowSeconds,
            ]);
        }

        $response = $next($request);

        return $response->withHeaders([
            'X-RateLimit-Limit' => $this->maxRequests,
            'X-RateLimit-Remaining' => max(0, $this->maxRequests - $currentCount),
        ]);
    }

    private function resolveKey(Request $request): string
    {
        // Rate limit by tenant (from JWT) or by IP for unauthenticated requests
        $tenantId = $request->attributes->get('tenant_id');

        if ($tenantId) {
            return "rate_limit:tenant:{$tenantId}";
        }

        return 'rate_limit:ip:' . $request->ip();
    }

    private function incrementCounter(string $key): int
    {
        $count = Redis::incr($key);

        if ($count === 1) {
            Redis::expire($key, $this->windowSeconds);
        }

        return $count;
    }
}
