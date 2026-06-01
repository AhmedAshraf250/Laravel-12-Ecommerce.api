<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // register
    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        return $this->processRegistration($validatedData, 'user');
    }

    protected function processRegistration(array $data, string $type)
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'type' => $type,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => ucfirst($type) . ' registered successfully',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 201);
    }
    // login
    public function login(Request $request)
    {
        // dd('login reached');
        // Validate the request data
        $validatedData = $request->validate([
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8',
        ]);

        // Find the user by email
        $user = User::where('email', $validatedData['email'])->first();

        // Check if the user exists and the password is correct
        if (!$user || !Hash::check($validatedData['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Generate an API token for the user
        $token = $user->createToken('auth_token')->plainTextToken;

        // dd('before json response');
        // Return the user and token in the response
        return response()->json([
            'message' => 'User logged in successfully',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 200);
    }

    // logout
    public function logout(Request $request)
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'User logged out successfully',
        ], 200);
    }

    // me
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ], 200);
    }

    // session info
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
}
