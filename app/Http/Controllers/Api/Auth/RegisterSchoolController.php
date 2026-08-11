<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterSchoolRequest;
use App\Services\SchoolRegistrationService;

class RegisterSchoolController extends Controller
{
    /**
     * Registers a brand-new school AND its first admin user (the proprietor)
     * in one request. This is the PUBLIC, self-serve entry point for a new
     * school signing up to the platform on their own.
     */
    public function __invoke(RegisterSchoolRequest $request, SchoolRegistrationService $registrationService)
    {
        [$school, $user] = $registrationService->register($request->validated());

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
