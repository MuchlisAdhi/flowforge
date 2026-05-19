<?php

use App\Models\User;
use App\Models\Tenant;
use App\Services\JwtService;

beforeEach(function () {
    config(['jwt.secret' => 'test-secret-key-for-unit-tests']);
    config(['jwt.ttl' => 60]);
    config(['app.url' => 'http://localhost']);
    $this->service = new JwtService();
});

describe('JwtService', function () {

    it('generates a valid JWT token', function () {
        $user = new User();
        $user->id = 'user-123';
        $user->tenant_id = 'tenant-456';
        $user->role = 'admin';
        $user->email = 'admin@test.com';

        $token = $this->service->generateToken($user);

        expect($token)->toBeString();
        expect($token)->toContain('.');

        // JWT has 3 parts separated by dots
        $parts = explode('.', $token);
        expect($parts)->toHaveCount(3);
    });

    it('validates a token it generated', function () {
        $user = new User();
        $user->id = 'user-789';
        $user->tenant_id = 'tenant-abc';
        $user->role = 'editor';
        $user->email = 'editor@test.com';

        $token = $this->service->generateToken($user);
        $payload = $this->service->validateToken($token);

        expect($payload)->not->toBeNull();
        expect($payload->sub)->toBe('user-789');
        expect($payload->tenant_id)->toBe('tenant-abc');
        expect($payload->role)->toBe('editor');
        expect($payload->email)->toBe('editor@test.com');
    });

    it('returns null for invalid token', function () {
        $payload = $this->service->validateToken('invalid.token.here');
        expect($payload)->toBeNull();
    });

    it('returns null for empty token', function () {
        $payload = $this->service->validateToken('');
        expect($payload)->toBeNull();
    });

    it('returns null for expired token', function () {
        // Set TTL to 0 to create an immediately expired token
        config(['jwt.ttl' => 0]);
        $service = new JwtService();

        $user = new User();
        $user->id = 'user-exp';
        $user->tenant_id = 'tenant-exp';
        $user->role = 'viewer';
        $user->email = 'expired@test.com';

        $token = $service->generateToken($user);

        // Token created with 0 TTL is already expired
        sleep(1);
        $payload = $service->validateToken($token);
        expect($payload)->toBeNull();
    });

    it('includes correct issuer claim', function () {
        $user = new User();
        $user->id = 'user-iss';
        $user->tenant_id = 'tenant-iss';
        $user->role = 'admin';
        $user->email = 'iss@test.com';

        $token = $this->service->generateToken($user);
        $payload = $this->service->parseToken($token);

        expect($payload->iss)->toBe('http://localhost');
    });

    it('token includes expiration claim', function () {
        $user = new User();
        $user->id = 'user-exp2';
        $user->tenant_id = 'tenant-exp2';
        $user->role = 'admin';
        $user->email = 'exp2@test.com';

        $token = $this->service->generateToken($user);
        $payload = $this->service->parseToken($token);

        expect($payload->exp)->toBeGreaterThan(time());
        expect($payload->exp)->toBeLessThanOrEqual(time() + 3601); // 60 min + 1s tolerance
    });

    it('getTokenTTL returns configured value', function () {
        expect($this->service->getTokenTTL())->toBe(60);
    });

    it('throws when secret is not configured', function () {
        config(['jwt.secret' => '']);

        expect(fn () => new JwtService())
            ->toThrow(\InvalidArgumentException::class, 'not configured');
    });
});
