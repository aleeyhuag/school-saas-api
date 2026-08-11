<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

/**
 * Runs the three time-based billing checks in one daily pass:
 * expiring trials, starting/enforcing the renewal grace window, and
 * sending trial-ending reminders. Kept as one command rather than
 * three separate ones since they're always run together on the same
 * schedule — see routes/console.php for the actual schedule entry.
 */
class ProcessBillingLifecycle extends Command
{
    protected $signature = 'billing:process-lifecycle';

    protected $description = 'Expires lapsed trials/subscriptions and sends trial-ending reminders';

    public function handle(SubscriptionService $subscriptionService): int
    {
        $expiredTrials = $subscriptionService->expireTrials();
        $this->info("Expired trials: {$expiredTrials}");

        $renewals = $subscriptionService->processRenewals();
        $this->info("Entered grace period: {$renewals['entered_grace']}, locked after grace: {$renewals['locked']}");

        $reminders = $subscriptionService->sendTrialReminders();
        $this->info("Trial-ending reminders sent: {$reminders}");

        return self::SUCCESS;
    }
}
