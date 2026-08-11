<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function __invoke()
    {
        request()->validate(['email' => ['required', 'email']]);

        // Deliberately the SAME response whether or not the email
        // exists — don't let this endpoint be used to check which
        // emails have accounts on the platform.
        Password::sendResetLink(request()->only('email'));

        return response()->json([
            'message' => 'If an account exists for that email, a password reset link has been sent.',
        ]);
    }
}
