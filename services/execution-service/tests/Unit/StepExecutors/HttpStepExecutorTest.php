<?php

use App\Services\StepExecutors\HttpStepExecutor;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->executor = new HttpStepExecutor();
});

describe('HttpStepExecutor', function () {

    it('executes GET request successfully', function () {
        Http::fake([
            'https://api.example.com/data' => Http::response(['users' => []], 200),
        ]);

        $result = $this->executor->execute([
            'method' => 'GET',
            'url' => 'https://api.example.com/data',
            'headers' => ['Accept' => 'application/json'],
        ]);

        expect($result)->toHaveKey('status_code');
        expect($result['status_code'])->toBe(200);
        expect($result)->toHaveKey('body');
    });

    it('executes POST request with body', function () {
        Http::fake([
            'https://api.example.com/users' => Http::response(['id' => '123'], 201),
        ]);

        $result = $this->executor->execute([
            'method' => 'POST',
            'url' => 'https://api.example.com/users',
            'headers' => ['Content-Type' => 'application/json'],
            'body' => ['name' => 'Test User', 'email' => 'test@test.com'],
        ]);

        expect($result['status_code'])->toBe(201);
    });

    it('throws on HTTP 500 error response', function () {
        Http::fake([
            'https://api.example.com/fail' => Http::response('Internal Server Error', 500),
        ]);

        expect(fn () => $this->executor->execute([
            'method' => 'GET',
            'url' => 'https://api.example.com/fail',
            'headers' => [],
        ]))->toThrow(RuntimeException::class, 'returned status 500');
    });

    it('throws on HTTP 404', function () {
        Http::fake([
            'https://api.example.com/missing' => Http::response('Not Found', 404),
        ]);

        expect(fn () => $this->executor->execute([
            'method' => 'GET',
            'url' => 'https://api.example.com/missing',
            'headers' => [],
        ]))->toThrow(RuntimeException::class, 'returned status 404');
    });

    it('throws on connection timeout', function () {
        Http::fake([
            'https://api.example.com/slow' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection timed out'),
        ]);

        expect(fn () => $this->executor->execute([
            'method' => 'GET',
            'url' => 'https://api.example.com/slow',
            'headers' => [],
        ]))->toThrow(RuntimeException::class, 'connection failed');
    });

    it('throws when URL is empty', function () {
        expect(fn () => $this->executor->execute([
            'method' => 'GET',
            'url' => '',
            'headers' => [],
        ]))->toThrow(RuntimeException::class, 'URL is required');
    });

    it('supports all HTTP methods', function () {
        Http::fake([
            '*' => Http::response(['ok' => true], 200),
        ]);

        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $result = $this->executor->execute([
                'method' => $method,
                'url' => 'https://api.example.com/test',
                'headers' => [],
            ]);
            expect($result['status_code'])->toBe(200);
        }
    });

    it('throws on unsupported HTTP method', function () {
        expect(fn () => $this->executor->execute([
            'method' => 'TRACE',
            'url' => 'https://api.example.com/test',
            'headers' => [],
        ]))->toThrow(RuntimeException::class, 'Unsupported HTTP method');
    });

    it('truncates large response body', function () {
        $largeBody = str_repeat('x', 100000); // 100KB
        Http::fake([
            '*' => Http::response($largeBody, 200),
        ]);

        $result = $this->executor->execute([
            'method' => 'GET',
            'url' => 'https://api.example.com/large',
            'headers' => [],
        ]);

        // Body should be truncated to 65535 chars (64KB)
        expect(strlen($result['body']))->toBeLessThanOrEqual(65535);
    });
});
