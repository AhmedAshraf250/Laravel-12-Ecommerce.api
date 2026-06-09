<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

abstract class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'device_name' => 'nullable|string|max:100',
        ]);

        return $this->processRegistration($validatedData, $this->expectedUserType());
    }

    protected function processRegistration(array $data, string $type)
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'type' => $type,
        ]);

        $token = $this->issueTokenForDevice(
            $user,
            $this->resolveDeviceName($data['device_name'] ?? null),
        );

        return response()->json([
            'message' => ucfirst($type) . ' registered successfully',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 201);
    }
    public function login(Request $request)
    {
        $validatedData = $request->validate([
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8',
            'device_name' => 'nullable|string|max:100',
        ]);

        $user = User::where('email', $validatedData['email'])->first();

        if (!$user || !Hash::check($validatedData['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        if ($user->type !== $this->expectedUserType()) {
            return response()->json([
                'message' => 'You are not allowed to login from this endpoint.',
            ], 403);
        }

        $token = $this->issueTokenForDevice(
            $user,
            $this->resolveDeviceName($validatedData['device_name'] ?? $request->userAgent()),
        );

        return response()->json([
            'message' => 'User logged in successfully',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 200);
    }

    abstract protected function expectedUserType(): string;

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'User logged out successfully',
        ], 200);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ], 200);
    }

    public function sessionInfo(Request $request)
    {
        $currentToken = $request->user()->currentAccessToken();

        return response()->json([
            'token' => [
                'id' => $currentToken?->id,
                'name' => $currentToken?->name,
                'abilities' => $currentToken?->abilities ?? [],
                'last_used_at' => $currentToken?->last_used_at,
                'expires_at' => $currentToken?->expires_at,
                'created_at' => $currentToken?->created_at,
            ],
            'user' => $request->user(),
        ], 200);
    }

    protected function issueTokenForDevice(User $user, string $deviceName): string
    {
        $tokenName = $this->tokenNameForDevice($deviceName);

        $user->tokens()->where('name', $tokenName)->delete();

        return $user->createToken($tokenName)->plainTextToken;
    }

    protected function tokenNameForDevice(string $deviceName): string
    {
        return "{$this->expectedUserType()}:{$deviceName}";
    }

    protected function resolveDeviceName(?string $deviceName): string
    {
        $deviceName = trim((string) $deviceName);

        return $deviceName !== '' ? $deviceName : 'default-device';
    }
}
