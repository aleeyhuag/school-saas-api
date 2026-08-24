<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CurrentUserController extends Controller
{
    /**
     * Returns the currently authenticated user, their school, and their
     * roles. Every client (web + mobile) calls this right after login
     * (or app startup, using a stored token) to know who's signed in
     * and what they're allowed to see.
     */
    public function __invoke(Request $request)
    {
        $user = $request->user()->load('school');

        $accessibleSchools = $user->hasRole('proprietor')
            ? $user->accessibleSchools()
                ->select('schools.id', 'schools.name', 'schools.is_active')
                ->orderBy('schools.name')
                ->get()
            : collect();

        $billingReasons = ['trial_expired', 'subscription_expired'];
        $billingLocked = $user->school_id
            && ($user->hasRole('proprietor') || $user->hasRole('principal'))
            && $user->school?->is_active === false
            && in_array($user->school?->deactivation_reason, $billingReasons, true);

        return response()->json([
            'user' => $user->only([
                'id',
                'name',
                'email',
                'phone',
                'school_id',
                'status',
            ]),
            'school' => $user->school,
            'roles' => $user->getRoleNames(),
            'accessible_schools' => $accessibleSchools,
            'billing_locked' => $billingLocked,
        ]);
    }
}