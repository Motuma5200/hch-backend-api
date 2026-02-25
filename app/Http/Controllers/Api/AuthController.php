<?php

namespace App\Http\Controllers\Api;
use App\Models\User;                     
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
            'role'                  => ['required', 'string', 'in:client,doctor,pharmacy_admin'],
            'organisation'          => ['nullable', 'string', 'max:255'],
            'hospital_id'           => ['nullable', 'integer', 'exists:hospitals,id'],
            'id_document'           => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
        ]);
        $role = $validated['role'];
        $organisation = $validated['organisation'] ?? null;

        // If registering as pharmacy_admin, require hospital_id
        if ($role === 'pharmacy_admin' && empty($validated['hospital_id'])) {
            return response()->json(['message' => 'hospital_id is required for pharmacy_admin registration'], 422);
        }

        // Auto-approve clients; other roles require admin approval
        $approved = $role === 'client';

        $user = User::create([
            'name'         => $validated['name'],
            'email'        => $validated['email'],
            'password'     => Hash::make($validated['password']),
            'role'         => $role,
            'organisation' => $organisation,
            'hospital_id'  => $validated['hospital_id'] ?? null,
            'approved'     => $approved,
        ]);

        // Handle optional ID document upload
        if ($request->hasFile('id_document')) {
            try {
                $path = $request->file('id_document')->store('id_documents', 'public');
                $user->id_document = $path;
                $user->save();
            } catch (\Throwable $e) {
                // If file storage fails, delete created user and return error
                $user->delete();
                return response()->json(['message' => 'Failed to store uploaded document'], 500);
            }
        }

        // Only issue token immediately if account is auto-approved
        $token = null;
        if ($approved) {
            $token = $user->createToken('auth_token')->plainTextToken;
        }

        $response = [
            'user'    => $user->only('id', 'name', 'email', 'role', 'organisation', 'approved', 'id_document'),
            'message' => 'Account created successfully',
        ];
        if ($token) $response['token'] = $token;

        return response()->json($response, 201);
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

        $passwordValid = false;
        try {
            $passwordValid = Hash::check($credentials['password'], $user->password);
        } catch (\RuntimeException $e) {
            // Legacy stored password not in bcrypt format (plain-text or other).
            // Fall back to direct comparison and upgrade to hashed password on success.
            if ($user->password === $credentials['password']) {
                $user->password = Hash::make($credentials['password']);
                $user->save();
                $passwordValid = true;
            }
        }

        if (!$passwordValid) {
            return response()->json([
                'message' => 'The provided credentials do not match our records.'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Also log the user in to create a session cookie for SPA auth (Sanctum)
        try {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();
        } catch (\Throwable $e) {
            // Ignore session errors; token login still works
        }

        return response()->json([
            'user'  => $user->only('id', 'name', 'email', 'role', 'organisation', 'approved'),
            'token' => $token,
            'message' => 'Login successful',
        ]);
    }
}