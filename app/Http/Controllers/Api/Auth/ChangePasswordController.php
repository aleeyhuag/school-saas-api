<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ChangePasswordController extends Controller
{
    /**
     * Self-service password change — any logged-in user (any role).
     * Requires the CURRENT password, so a stolen/still-logged-in
     * session token alone isn't enough to take over an account.
     */
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
