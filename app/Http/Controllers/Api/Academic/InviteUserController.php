<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\InviteUserRequest;
use App\Models\User;
use App\Notifications\AccountSetupNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Services\AccountActionTokenService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteUserController extends Controller
{
    public function __invoke(InviteUserRequest $request, AccountActionTokenService $accountActionTokens)
    {
        $validated = $request->validated();
        $inviter = Auth::user();
        $seniorRoles = ['proprietor', 'principal', 'bursar', 'exam_officer'];

        if (in_array($validated['role'], $seniorRoles) && ! $inviter->hasAnyRole(['proprietor', 'principal'])) {
            throw ValidationException::withMessages([
                'role' => ['Only the proprietor or principal can appoint a proprietor, principal, bursar, or exam officer.'],
            ]);
        }

        $temporaryPassword = Str::random(12);
        $user = User::create([
            'school_id' => $inviter->school_id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($temporaryPassword),
            'status' => 'approved',
        ]);
        $user->assignRole($validated['role']);

        try {
            $token = $accountActionTokens->issue($user, 'account_setup');
            $user->notify(new AccountSetupNotification($token, $inviter->school->name));
        } catch (\Throwable $e) {
            // Never put the temporary password in a successful API response.
            // If email delivery is unavailable, return a clear failure so the
            // admin can retry after mail is configured instead of leaking a
            // credential into browser/network logs.
            report($e);
            $user->delete();
            throw ValidationException::withMessages([
                'email' => ['The account could not be created because the setup email could not be sent. Please check the school email settings and try again.'],
            ]);
        }

        return response()->json([
            'message' => 'User invited successfully. A secure password setup link has been emailed to them.',
            'user' => $user->only(['id', 'name', 'email', 'phone', 'status']),
            'role' => $validated['role'],
            'setup_link_sent' => true,
        ], 201);
    }
}
