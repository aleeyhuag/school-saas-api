<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks every school-scoped request once the school is disabled or its
 * subscription/trial has really expired. Billing-locked Proprietor/Principal
 * users get only the two auth endpoints required to establish/close their
 * session; the Billing routes themselves deliberately omit this middleware.
 */
class EnsureSchoolIsActive
{
    protected const BILLING_CARVEOUT_ROUTES = ['auth.me', 'auth.logout'];

    public function __construct(protected SubscriptionService $subscriptionService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->school_id) {
            $school = School::where('id', $user->school_id)
                ->select('id', 'is_active', 'deactivation_reason')
                ->first();

            if ($school) {
                $school = $this->subscriptionService->enforceLiveExpiry($school);
            }

            if ($school && $school->is_active === false) {
                $billingReasons = ['trial_expired', 'subscription_expired'];
                $canManageBilling = $user->hasRole('proprietor') || $user->hasRole('principal');
                $isBillingLock = in_array($school->deactivation_reason, $billingReasons, true);
                $isCarvedOutRoute = $request->routeIs(...self::BILLING_CARVEOUT_ROUTES);

                if (! ($canManageBilling && $isBillingLock && $isCarvedOutRoute)) {
                    $messages = [
                        'trial_expired' => 'Your free trial has ended. Subscribe to keep access — nothing about your data has changed.',
                        'subscription_expired' => 'Your subscription has lapsed. Renew to regain access — nothing about your data has changed.',
                    ];

                    return response()->json([
                        'message' => $messages[$school->deactivation_reason] ?? 'Your school has been disabled. Please contact your proprietor or principal.',
                        'code' => 'school_disabled',
                        'reason' => $school->deactivation_reason,
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
