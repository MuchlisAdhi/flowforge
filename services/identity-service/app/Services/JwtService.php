<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use InvalidArgumentException;

class JwtService
{
    private string $secret;
    private int $ttl;
    private string $algorithm = 'HS256';

    public function __construct()
    {
        $this->secret = config('jwt.secret');
        $this->ttl = (int) config('jwt.ttl', 60);

        if (empty($this->secret)) {
            throw new InvalidArgumentException('JWT secret is not configured');
        }
    }

    public function generateToken(User $user): string
    {
        $now = time();

        $payload = [
            'iss' => config('app.url'),
            'sub' => $user->id,
            'iat' => $now,
            'exp' => $now + ($this->ttl * 60),
            'tenant_id' => $user->tenant_id,
            'role' => $user->role,
            'email' => $user->email,
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    public function parseToken(string $token): object
    {
        return JWT::decode($token, new Key($this->secret, $this->algorithm));
    }

    public function validateToken(string $token): ?object
    {
        try {
            return $this->parseToken($token);
        } catch (ExpiredException) {
            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function getTokenTTL(): int
    {
        return $this->ttl;
    }
}
