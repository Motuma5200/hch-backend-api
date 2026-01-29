<?php

namespace App\Http\Controllers\Api;
use App\Models\User;                     
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
 
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out']);
    }

    public function user(Request $request)
    {
        return $request->user();
    }

public function register(Request $request)
    {
        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => $validated['password'],   // plain text
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token'   => $token,
            'user'    => $user->only('id', 'name', 'email'),
            'message' => 'Account created successfully',
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'The provided credentials do not match our records.'
            ], 401);
        }

        if ($user->password !== $credentials['password']) {
            return response()->json([
                'message' => 'The provided credentials do not match our records.'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'  => $user->only('id', 'name', 'email'),
            'token' => $token,
            'message' => 'Login successful',
        ]);
    }
}