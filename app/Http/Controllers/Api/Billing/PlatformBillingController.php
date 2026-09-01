<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;

class PlatformBillingController extends Controller
{
    public function __construct(protected SubscriptionService $subscriptionService) {}

    public function plans()
    {
        return response()->json(Plan::orderBy('amount_kobo')->get());
    }

    public function updatePlan(Plan $plan)
    {
        $validated = request()->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'amount_kobo' => ['sometimes', 'integer', 'min:0'],
            'duration_months' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $plan->update($validated);

        return response()->json($plan);
    }

    /**
     * Subscription status breakdown across every school — the
     * platform-wide "who's paid, who hasn't" view.
     */
    public function overview()
    {
        $counts = Subscription::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'by_status' => $counts,
            'pending_review_count' => Payment::where('status', 'pending_review')->count(),
        ]);
    }

    /**
     * The review queue — oldest first, so it processes like an
     * actual inbox rather than jumping around.
     */
    public function pendingPayments()
    {
        $payments = Payment::with('school', 'plan')
            ->where('status', 'pending_review')
            ->oldest()
            ->get();

        return response()->json($payments);
    }

    public function confirmPayment(Payment $payment)
    {
        if ($payment->status !== 'pending_review') {
            return response()->json(['message' => 'This payment has already been reviewed.'], 422);
        }

        $this->subscriptionService->confirmPayment($payment, Auth::id());

        return response()->json(['message' => 'Payment confirmed — subscription updated.']);
    }

    public function rejectPayment(Payment $payment)
    {
        if ($payment->status !== 'pending_review') {
            return response()->json(['message' => 'This payment has already been reviewed.'], 422);
        }

        $validated = request()->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->subscriptionService->rejectPayment($payment, Auth::id(), $validated['reason']);

        return response()->json(['message' => 'Payment rejected — the school has been notified.']);
    }

    /**
     * Manual re-run of the same three steps `billing:process-lifecycle`
     * runs automatically at 6am daily. The scheduler is the normal
     * path — this exists so Super Admin isn't stuck waiting until
     * tomorrow morning to confirm a fix took effect, or to catch up
     * immediately after downtime. Runs all three every time, in the
     * same order as the scheduled command, and reports what each step
     * did rather than just "done".
     */
    public function runLifecycleNow()
    {
        $expired = $this->subscriptionService->expireTrials();
        $renewals = $this->subscriptionService->processRenewals();
        $reminders = $this->subscriptionService->sendTrialReminders();

        return response()->json([
            'trials_expired' => $expired,
            'renewals_entered_grace' => $renewals['entered_grace'],
            'renewals_locked' => $renewals['locked'],
            'trial_reminders_sent' => $reminders,
            'ran_at' => now()->toIso8601String(),
        ]);
    }
}
