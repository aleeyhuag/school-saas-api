<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterSchoolRequest;
use App\Services\SchoolRegistrationService;

class RegisterSchoolController extends Controller
{
    public const TERMS_VERSION = '2026-08-11';
    public const PRIVACY_VERSION = '2026-08-11';
    /**
     * Registers a brand-new school AND its first admin user (the proprietor)
     * in one request. This is the PUBLIC, self-serve entry point for a new
     * school signing up to the platform on their own.
     */
    public function __invoke(RegisterSchoolRequest $request, SchoolRegistrationService $registrationService)
    {
        [$school, $user] = $registrationService->register($request->validated());

        $user->forceFill([
            'terms_version' => self::TERMS_VERSION,
            'privacy_version' => self::PRIVACY_VERSION,
            'legal_accepted_at' => now(),
            'legal_accepted_ip' => $request->ip(),
            'legal_accepted_user_agent' => substr((string) $request->userAgent(), 0, 65535),
        ])->save();

        $token = $user->createToken('registration')->plainTextToken;

        return response()->json([
            'message' => 'School registered successfully.',
            'school' => $school,
            'user' => $user->only(['id', 'name', 'email', 'status']),
            'roles' => $user->getRoleNames(),
            'token' => $token,
        ], 201);
    }
}
