<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        private readonly JwtService $jwtService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Create tenant if new registration
        $tenant = Tenant::create([
            'name' => $validated['tenant_name'],
            'slug' => str($validated['tenant_name'])->slug(),
            'is_active' => true,
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'admin', // First user of tenant is admin
            'is_active' => true,
        ]);

        $token = $this->jwtService->generateToken($user);

        return response()->json([
            'message' => 'Registration successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'tenant_id' => $user->tenant_id,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => $this->jwtService->getTokenTTL() * 60,
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Account is deactivated',
            ], 403);
        }

        if (! $user->tenant->is_active) {
            return response()->json([
                'message' => 'Tenant account is deactivated',
            ], 403);
        }

        $token = $this->jwtService->generateToken($user);

        return response()->json([
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'tenant_id' => $user->tenant_id,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => $this->jwtService->getTokenTTL() * 60,
            ],
        ]);
    }

    public function me(): JsonResponse
    {
        $user = request()->attributes->get('auth_user');

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'tenant_id' => $user->tenant_id,
                'tenant' => [
                    'id' => $user->tenant->id,
                    'name' => $user->tenant->name,
                ],
            ],
        ]);
    }

    public function validateToken(): JsonResponse
    {
        // If middleware passed, token is valid
        $user = request()->attributes->get('auth_user');

        return response()->json([
            'valid' => true,
            'data' => [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'role' => $user->role,
                'email' => $user->email,
            ],
        ]);
    }
}
