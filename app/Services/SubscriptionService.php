<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Notifications\TrialEndingNotification;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function startTrial(School $school): Subscription
    {
        return Subscription::create([
            'school_id' => $school->id,
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(7),
        ]);
    }

    /**
     * A Proprietor submitting "I've made this transfer" — creates a
     * pending_review Payment. Does NOT touch the subscription's
     * status; that only changes once Super Admin confirms it (see
     * confirmPayment() below).
     */
    /**
     * Returns the school's subscription, repairing older/incomplete school
     * records that do not have one yet.
     *
     * Every normal school-registration path already creates a subscription.
     * This guard is important for schools created before billing was added,
     * imported/test data, and historical records that were created without
     * the subscription row.
     */
    public function ensureSubscription(School $school): Subscription
    {
        $subscription = $school->subscription;

        if ($subscription) {
            return $subscription;
        }

        return Subscription::firstOrCreate(
            ['school_id' => $school->id],
            [
                'status' => 'trialing',
                'trial_ends_at' => now()->addDays(7),
            ],
        );
    }

    /**
     * Returns the school's stable payment reference, repairing legacy
     * records where the billing migration left the value null.
     *
     * The payments table requires reference_code, so silently passing a
     * null school payment_reference_code would fail at INSERT time.
     */
    public function ensurePaymentReferenceCode(School $school): string
    {
        if ($school->payment_reference_code) {
            return $school->payment_reference_code;
        }

        do {
            $referenceCode = 'SCH-'.strtoupper(\Illuminate\Support\Str::random(6));
        } while (
            School::query()
                ->where('payment_reference_code', $referenceCode)
                ->whereKeyNot($school->getKey())
                ->exists()
        );

        $school->forceFill([
            'payment_reference_code' => $referenceCode,
        ])->save();

        return $referenceCode;
    }

    public function submitBankTransferPayment(School $school, Plan $plan, string $proofPath): Payment
    {
        $subscription = $this->ensureSubscription($school);
        $referenceCode = $this->ensurePaymentReferenceCode($school);

        return Payment::create([
            'school_id' => $school->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'amount_kobo' => $plan->amount_kobo,
            'method' => 'bank_transfer',
            'status' => 'pending_review',
            'reference_code' => $referenceCode,
            'proof_path' => $proofPath,
        ]);
    }

    /**
     * Confirms a payment — either a Super Admin manually reviewing a
     * bank transfer (reviewerId set), or an automated Paystack
     * webhook confirming a card charge (reviewerId null, since no
     * human reviewed it). Activates/extends the subscription and
     * reactivates the school if it had been locked, either way.
     */
    public function confirmPayment(Payment $payment, ?int $reviewerId = null): void
    {
        DB::transaction(function () use ($payment, $reviewerId) {
            $payment->update([
                'status' => 'success',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ]);

            $subscription = $payment->subscription;
            $plan = $payment->plan;

            // Extend from whichever is later: the current period end
            // (renewing before it lapses just adds on top of what's
            // left) or right now (renewing after a lapse starts
            // fresh from today, not from some date in the past).
            $extendFrom = $subscription->current_period_ends_at && $subscription->current_period_ends_at->isFuture()
                ? $subscription->current_period_ends_at
                : now();

            $subscription->update([
                'plan_id' => $plan->id,
                'status' => 'active',
                'current_period_ends_at' => $extendFrom->copy()->addMonths($plan->duration_months),
                'grace_ends_at' => null,
            ]);

            $school = $payment->school;
            if (! $school->is_active) {
                $school->update(['is_active' => true, 'deactivation_reason' => null]);
            }

            $this->notifyProprietors($school, new PaymentConfirmedNotification($plan, $school->name));
        });
    }

    public function rejectPayment(Payment $payment, int $reviewerId, string $reason): void
    {
        $payment->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->notifyProprietors($payment->school, new PaymentRejectedNotification($reason, $payment->school->name));
    }

    /**
     * Scheduled daily — trials that have run out get locked
     * immediately (confirmed: no grace period on the initial trial,
     * unlike a missed renewal below, since there's no payment
     * potentially already "in flight" to wait for).
     */
    public function expireTrials(): int
    {
        $subscriptions = Subscription::with('school')
            ->where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->get();

        foreach ($subscriptions as $subscription) {
            $subscription->update(['status' => 'expired']);
            $subscription->school->update([
                'is_active' => false,
                'deactivation_reason' => 'trial_expired',
            ]);
        }

        return $subscriptions->count();
    }

    /**
     * Scheduled daily — handles both ends of the renewal grace
     * window (confirmed: 3 days) in one pass: starts it the moment a
     * paid period lapses, then enforces it once that's used up
     * without a confirmed renewal.
     */
    public function processRenewals(): array
    {
        $justLapsed = Subscription::with('school')
            ->where('status', 'active')
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<', now())
            ->get();

        foreach ($justLapsed as $subscription) {
            $subscription->update([
                'status' => 'past_due',
                'grace_ends_at' => now()->addDays(3),
            ]);
        }

        $graceExpired = Subscription::with('school')
            ->where('status', 'past_due')
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<', now())
            ->get();

        foreach ($graceExpired as $subscription) {
            $subscription->update(['status' => 'expired']);
            $subscription->school->update([
                'is_active' => false,
                'deactivation_reason' => 'subscription_expired',
            ]);
        }

        return ['entered_grace' => $justLapsed->count(), 'locked' => $graceExpired->count()];
    }

    /**
     * Scheduled daily — reminders at 2 days left and on the final day.
     */
    public function sendTrialReminders(): int
    {
        $subscriptions = Subscription::with('school')
            ->where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->get()
            ->filter(function ($subscription) {
                $daysLeft = (int) now()->startOfDay()->diffInDays($subscription->trial_ends_at->startOfDay(), false);
                return $daysLeft === 2 || $daysLeft === 0;
            });

        $count = 0;
        foreach ($subscriptions as $subscription) {
            $daysLeft = (int) now()->startOfDay()->diffInDays($subscription->trial_ends_at->startOfDay(), false);
            $this->notifyProprietors($subscription->school, new TrialEndingNotification($daysLeft, $subscription->school->name));
            $count++;
        }

        return $count;
    }

    protected function notifyProprietors(School $school, $notification): void
    {
        $school->users()
            ->whereHas('roles', fn ($q) => $q->where('name', 'proprietor'))
            ->get()
            ->each(fn ($proprietor) => $proprietor->notify($notification));
    }
}
