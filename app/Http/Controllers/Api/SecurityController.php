<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SecurityController extends Controller
{
    public function changePassword(Request $request)
    {
        // 1. Enforce strict validation rules
        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => [
                'required', 
                'string', 
                Password::min(8)->mixedCase()->numbers(), 
                'confirmed' // Looks for new_password_confirmation in the payload
            ],
        ]);

        $user = $request->user(); // Works perfectly for Doctor or Client models via Sanctum

        // 2. Cryptographically verify the old password matches the database
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'errors' => [
                    'current_password' => ['The provided password does not match our records.']
                ]
            ], 422);
        }

        // 3. Hash the new password and commit to the database
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Password updated successfully.'
        ], 200);
    }
}