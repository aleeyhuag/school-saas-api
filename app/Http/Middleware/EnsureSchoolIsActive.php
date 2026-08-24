<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks EVERY school-scoped request the moment a school is
 * deactivated — not just at login. Without this, a user already
 * logged in (holding a valid Sanctum token) would keep working
 * normally even after a super_admin disabled their school, since a
 * token doesn't expire just because the school's status changed.
 *
 * Runs AFTER 'auth:sanctum' in the route's middleware array (so
 * Auth::user() is already resolved) — add it right alongside
 * 'auth:sanctum' in every route group except the public auth routes
 * and the super_admin-only platform routes (super_admin has no
 * school_id, so this would be a no-op for them anyway, but they're
 * skipped for clarity).
 */
class EnsureSchoolIsActive
{
    public function __construct(protected SubscriptionService $subscriptionService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->school_id) {
            // Load fresh rather than trusting a stale relation — the
            // whole point of this middleware is to catch a status
            // change that happened after the token was issued.
            $school = \App\Models\School::where('id', $user->school_id)
                ->select('id', 'is_active', 'deactivation_reason')
                ->first();

            // Stage 55 hotfix — don't just trust the cached is_active
            // flag. It's normally kept correct by a daily cron
            // (billing:process-lifecycle), but that's a single point
            // of failure: if it hasn't run yet for any reason, a
            // fully-expired trial/subscription would otherwise keep
            // working indefinitely. Re-derive live from trial_ends_at/
            // grace_ends_at on every request and self-heal is_active
            // immediately if it's stale. See SubscriptionService::
            // enforceLiveExpiry() for the full reasoning.
            if ($school) {
                $school = $this->subscriptionService->enforceLiveExpiry($school);
            }

            if ($school && $school->is_active === false) {
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

        return $next($request);
    }
}
