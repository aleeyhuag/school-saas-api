<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Services\AuditLogService;
use App\Services\SubscriptionService;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Logs in a user (from any role: proprietor, teacher, student, parent...)
     * and returns a Sanctum API token for the Flutter app / React web app
     * to use on subsequent requests.
     */
    public function __invoke(LoginRequest $request, AuditLogService $auditLogs, SubscriptionService $subscriptionService)
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status === 'disabled') {
            throw ValidationException::withMessages([
                'email' => ['This account has been disabled. Contact your school admin.'],
            ]);
        }

        if ($user->status === 'pending') {
            throw ValidationException::withMessages([
                'email' => ['This account is awaiting approval from your school admin.'],
            ]);
        }

        // school_id is null for super_admin (not tied to any school) —
        // only check for users who actually belong to one.
        if ($user->school_id) {
            $school = School::where('id', $user->school_id)
                ->select('id', 'is_active', 'deactivation_reason')
                ->first();

            if ($school) {
                $school = $subscriptionService->enforceLiveExpiry($school);
            }

            $billingReasons = ['trial_expired', 'subscription_expired'];
            $canManageBilling = $user->hasRole('proprietor') || $user->hasRole('principal');
            $isBillingLock = $school && in_array($school->deactivation_reason, $billingReasons, true);
            $isBlocked = $school && $school->is_active === false && ! ($canManageBilling && $isBillingLock);

            // Multi-branch nuance: a Proprietor's login is shared
            // across every branch they own (see BranchController). If
            // the branch they HAPPEN to be currently switched into is
            // the one that got disabled, that must not lock them out
            // of branches that are still fine — silently switch them
            // onto another active branch they still have access to
            // and let the login through, same self-unlock principle
            // as the billing exception above.
            if ($isBlocked && $isProprietor) {
                $activeBranchId = $user->accessibleSchools()
                    ->where('schools.id', '!=', $school->id)
                    ->where('schools.is_active', true)
                    ->value('schools.id');

                if ($activeBranchId) {
                    $user->update(['school_id' => $activeBranchId]);
                    $isBlocked = false;
                }
            }

            if ($isBlocked) {
                $messages = [
                    'trial_expired' => 'Your free trial has ended. Subscribe to keep access.',
                    'subscription_expired' => 'Your subscription has lapsed. Renew to regain access.',
                ];

                throw ValidationException::withMessages([
                    'email' => [$messages[$school->deactivation_reason] ?? 'Your school has been disabled. Please contact your proprietor or principal.'],
                ]);
            }
        }

        $deviceName = $validated['device_name'] ?? $request->userAgent() ?? 'unknown-device';

        $token = $user->createToken($deviceName)->plainTextToken;

        if ($user->school_id) {
            $auditLogs->record($request, 'login', 'User logged in successfully.', [
                'role' => $user->getRoleNames()->values()->all(),
            ]);
        }

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'school_id', 'status']),
            'roles' => $user->getRoleNames(),
            'token' => $token,
            'billing_locked' => $isBillingLock ?? false,
        ]);
    }
}
