<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\InviteUserRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteUserController extends Controller
{
    /**
     * Invites a new staff member or parent into the CURRENT logged-in
     * user's school (school_id is taken from the inviter, never from
     * the request body — this is what stops one school from creating
     * users inside another school).
     *
     * For now this generates a temporary password and returns it in
     * the response so you can test end-to-end without email/SMS set up
     * yet. Once you wire up an SMS/email gateway (Phase 2 of the
     * roadmap), replace the returned password with a "check your
     * email/SMS" message and actually send it instead.
     */
    public function __invoke(InviteUserRequest $request)
    {
        $validated = $request->validated();
        $inviter = Auth::user();

        // Senior/financial roles can only be appointed by the
        // Proprietor — this is an ownership-level power, distinct
        // from day-to-day school operation. A Principal can still
        // invite the operational roles (teachers, parents) freely.
        $seniorRoles = ['principal', 'bursar', 'exam_officer'];

        if (in_array($validated['role'], $seniorRoles) && ! $inviter->hasRole('proprietor')) {
            throw ValidationException::withMessages([
                'role' => ['Only the school proprietor can appoint a principal, bursar, or exam officer.'],
            ]);
        }

        $temporaryPassword = Str::random(10);

        $user = User::create([
            'school_id' => $inviter->school_id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($temporaryPassword),
            'status' => 'approved', // invited directly by an admin, so pre-approved
        ]);

        $user->assignRole($validated['role']);

        $user->notify(new \App\Notifications\TempPasswordNotification($temporaryPassword, $inviter->school->name));

        return response()->json([
            'message' => 'User invited successfully. Their login details have also been emailed to them.',
            'user' => $user->only(['id', 'name', 'email', 'phone', 'status']),
            'role' => $validated['role'],
            'temporary_password' => $temporaryPassword, // kept as a fallback in case the email doesn't land
        ], 201);
    }
}
