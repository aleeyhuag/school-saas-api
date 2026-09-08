<?php

namespace App\Console\Commands;

use App\Jobs\SendPromotionalEmailJob;
use App\Models\EmailCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseMarketingBatch extends Command
{
    protected $signature = 'marketing:release-batch {--batch=10}';
    protected $description = 'Send a controlled global batch of promotional emails without depending on a queue worker.';

    public function handle(): int
    {
        $remaining = max(1, (int) $this->option('batch'));
        $recipientIds = [];

        foreach (EmailCampaign::whereIn('status', ['queued', 'sending'])
            ->whereNull('cancelled_at')
            ->orderBy('id')
            ->get() as $campaign) {
            if ($remaining <= 0) {
                break;
            }

            $released = DB::transaction(function () use ($campaign, &$remaining) {
                $locked = EmailCampaign::whereKey($campaign->id)->lockForUpdate()->first();
                if (! $locked || $locked->status === 'cancelled' || $remaining <= 0) {
                    return collect();
                }

                // First recover recipients already released by the old queue-based
                // implementation, then release new pending recipients.
                $rows = $locked->recipients()
                    ->whereIn('status', ['queued', 'pending'])
                    ->orderByRaw("CASE WHEN status = 'queued' THEN 0 ELSE 1 END")
                    ->orderBy('id')
                    ->limit($remaining)
                    ->lockForUpdate()
                    ->get();

                if ($rows->isEmpty()) {
                    if ($locked->recipients()->whereIn('status', ['pending', 'queued'])->doesntExist()) {
                        $locked->update(['status' => 'completed', 'completed_at' => now()]);
                    }
                    return collect();
                }

                $locked->update([
                    'status' => 'sending',
                    'started_at' => $locked->started_at ?? now(),
                ]);

                foreach ($rows as $row) {
                    if ($row->status === 'pending') {
                        $row->update(['status' => 'queued', 'released_at' => now()]);
                    }
                    $remaining--;
                }

                return $rows;
            });

            foreach ($released as $row) {
                $recipientIds[] = $row->id;
            }
        }

        $sentOrFailed = 0;
        foreach ($recipientIds as $recipientId) {
            try {
                // Intentionally call the same job handler directly. This preserves
                // the existing unsubscribe/suppression/mail logic while removing
                // the unreliable database-queue dependency for marketing.
                (new SendPromotionalEmailJob((int) $recipientId))->handle();
            } catch (\Throwable $e) {
                $this->error("Marketing recipient {$recipientId} failed: {$e->getMessage()}");
            }
            $sentOrFailed++;
        }

        // Mark campaigns complete as soon as no work remains. This also recovers
        // campaigns whose final recipient was already queued before this patch.
        EmailCampaign::whereIn('status', ['sending', 'queued'])
            ->whereNull('cancelled_at')
            ->each(function (EmailCampaign $campaign) {
                if (! $campaign->recipients()->whereIn('status', ['pending', 'queued'])->exists()) {
                    $campaign->update(['status' => 'completed', 'completed_at' => now()]);
                }
            });

        $this->info('Processed '.count($recipientIds).' promotional email(s).');
        return self::SUCCESS;
    }
}
