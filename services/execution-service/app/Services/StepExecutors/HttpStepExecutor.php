<?php

namespace App\Services\StepExecutors;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpStepExecutor implements StepExecutorInterface
{
    private const TIMEOUT_SECONDS = 30;
    private const MAX_RESPONSE_SIZE = 65535; // 64KB max stored response

    public function execute(array $config, array $context = []): ?array
    {
        $method = strtoupper($config['method'] ?? 'GET');
        $url = $config['url'] ?? '';
        $headers = $config['headers'] ?? [];
        $body = $config['body'] ?? null;
        $timeout = $config['timeout_seconds'] ?? self::TIMEOUT_SECONDS;

        if (empty($url)) {
            throw new RuntimeException('HTTP step: URL is required');
        }

        try {
            $request = Http::timeout($timeout)
                ->withHeaders($headers);

            $response = match ($method) {
                'GET' => $request->get($url),
                'POST' => $request->post($url, $body),
                'PUT' => $request->put($url, $body),
                'PATCH' => $request->patch($url, $body),
                'DELETE' => $request->delete($url),
                default => throw new RuntimeException("Unsupported HTTP method: {$method}"),
            };

            if ($response->failed()) {
                throw new RuntimeException(
                    "HTTP {$method} {$url} returned status {$response->status()}: " .
                    substr($response->body(), 0, 500)
                );
            }

            return [
                'status_code' => $response->status(),
                'body' => substr($response->body(), 0, self::MAX_RESPONSE_SIZE),
                'headers' => $response->headers(),
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new RuntimeException("HTTP connection failed: {$e->getMessage()}");
        }
    }
}
