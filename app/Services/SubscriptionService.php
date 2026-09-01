<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentReview;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Notifications\TrialEndingNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        return DB::transaction(function () use ($school, $plan, $proofPath) {
            // Serialize submissions for this school. The frontend already
            // disables the button when a payment is pending, but the server
            // must enforce the rule too because two requests can arrive at
            // almost the same time.
            $lockedSchool = School::query()->whereKey($school->id)->lockForUpdate()->firstOrFail();

            $hasPending = Payment::query()
                ->where('school_id', $lockedSchool->id)
                ->where('status', 'pending_review')
                ->exists();

            if ($hasPending) {
                throw ValidationException::withMessages([
                    'payment' => ['You already have a payment awaiting review. Please wait for it to be confirmed or rejected before submitting another.'],
                ]);
            }

            $subscription = $this->ensureSubscription($lockedSchool);
            $referenceCode = $this->ensurePaymentReferenceCode($lockedSchool);

            // The amount and duration are taken from the server-side plan.
            // The browser never gets to choose the financial value stored in
            // the payment record. duration_months is a historical snapshot so
            // later edits to the plan cannot change what was purchased.
            return Payment::create([
                'school_id' => $lockedSchool->id,
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'amount_kobo' => $plan->amount_kobo,
                'duration_months' => $plan->duration_months,
                'method' => 'bank_transfer',
                'status' => 'pending_review',
                'reference_code' => $referenceCode,
                'proof_path' => $proofPath,
            ]);
        });
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
        $notification = DB::transaction(function () use ($payment, $reviewerId) {
            // Lock the payment so a repeated admin click or a Paystack webhook
            // cannot confirm the same payment twice and extend the subscription
            // twice.
            $lockedPayment = Payment::query()
                ->with(['subscription', 'plan', 'school'])
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->status !== 'pending_review') {
                return null;
            }

            $subscription = Subscription::query()
                ->whereKey($lockedPayment->subscription_id)
                ->lockForUpdate()
                ->firstOrFail();

            $plan = $lockedPayment->plan;
            $durationMonths = (int) ($lockedPayment->duration_months ?: $plan->duration_months);
            $previousStatus = $lockedPayment->status;
            $now = now();

            $lockedPayment->update([
                'status' => 'success',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => $now,
            ]);

            // Extend from whichever is later: the current period end or now.
            $extendFrom = $subscription->current_period_ends_at && $subscription->current_period_ends_at->isFuture()
                ? $subscription->current_period_ends_at
                : $now;

            $subscription->update([
                'plan_id' => $plan->id,
                'status' => 'active',
                'current_period_ends_at' => $extendFrom->copy()->addMonths($durationMonths),
                'grace_ends_at' => null,
            ]);

            $school = $lockedPayment->school;
            if (! $school->is_active) {
                $school->update(['is_active' => true, 'deactivation_reason' => null]);
            }

            PaymentReview::create([
                'payment_id' => $lockedPayment->id,
                'reviewer_id' => $reviewerId,
                'action' => 'confirmed',
                'previous_status' => $previousStatus,
                'new_status' => 'success',
                'reason' => null,
                'created_at' => $now,
            ]);

            return [$school, $plan];
        });

        if ($notification) {
            [$school, $plan] = $notification;
            $this->notifyProprietors($school, new PaymentConfirmedNotification($plan, $school->name));
        }
    }

    public function rejectPayment(Payment $payment, int $reviewerId, string $reason): void
    {
        $notification = DB::transaction(function () use ($payment, $reviewerId, $reason) {
            $lockedPayment = Payment::query()
                ->with('school')
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->status !== 'pending_review') {
                return null;
            }

            $now = now();
            $previousStatus = $lockedPayment->status;

            $lockedPayment->update([
                'status' => 'rejected',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => $now,
                'rejection_reason' => $reason,
            ]);

            PaymentReview::create([
                'payment_id' => $lockedPayment->id,
                'reviewer_id' => $reviewerId,
                'action' => 'rejected',
                'previous_status' => $previousStatus,
                'new_status' => 'rejected',
                'reason' => $reason,
                'created_at' => $now,
            ]);

            return $lockedPayment->school;
        });

        if ($notification) {
            $this->notifyProprietors($notification, new PaymentRejectedNotification($reason, $notification->name));
        }
    }

    /**
     * Stage 55 hotfix — derive security-sensitive expiry state from the
     * subscription dates on the request that is being served. The daily
     * scheduler remains the lifecycle/reminder backstop, but it must never
     * be the only thing capable of locking an expired trial.
     *
     * Trial expiry is immediate. A paid subscription that has entered
     * `past_due` keeps its existing grace-period semantics and is only locked
     * once grace_ends_at has passed.
     */
    public function enforceLiveExpiry(School $school): School
    {
        $subscription = $school->subscription;

        if (! $subscription) {
            return $school;
        }

        if (
            $subscription->status === 'trialing'
            && $subscription->trial_ends_at
            && $subscription->trial_ends_at->isPast()
        ) {
            $subscription->update(['status' => 'expired']);
            $school->update([
                'is_active' => false,
                'deactivation_reason' => 'trial_expired',
            ]);

            return $school->fresh();
        }

        if (
            $subscription->status === 'past_due'
            && $subscription->grace_ends_at
            && $subscription->grace_ends_at->isPast()
        ) {
            $subscription->update(['status' => 'expired']);
            $school->update([
                'is_active' => false,
                'deactivation_reason' => 'subscription_expired',
            ]);

            return $school->fresh();
        }

        return $school;
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
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['proprietor', 'principal']))
            ->get()
            ->each(fn ($user) => $user->notify($notification));
    }
}
