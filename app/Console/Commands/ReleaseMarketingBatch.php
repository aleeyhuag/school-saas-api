<?php
namespace App\Console\Commands;
use App\Jobs\SendPromotionalEmailJob;
use App\Models\EmailCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseMarketingBatch extends Command
{
    protected $signature = 'marketing:release-batch {--batch=10}';
    protected $description = 'Release a controlled global batch of promotional emails.';

    public function handle(): int
    {
        $remaining = max(1, (int) $this->option('batch'));
        $jobs = [];

        foreach (EmailCampaign::whereIn('status', ['queued', 'sending'])->whereNull('cancelled_at')->orderBy('id')->get() as $campaign) {
            if ($remaining <= 0) break;

            $released = DB::transaction(function () use ($campaign, &$remaining) {
                $locked = EmailCampaign::whereKey($campaign->id)->lockForUpdate()->first();
                if (! $locked || $locked->status === 'cancelled') return collect();

                $rows = $locked->recipients()->where('status', 'pending')->orderBy('id')->limit($remaining)->lockForUpdate()->get();
                if ($rows->isEmpty()) {
                    if ($locked->recipients()->whereIn('status', ['pending', 'queued'])->doesntExist()) {
                        $locked->update(['status' => 'completed', 'completed_at' => now()]);
                    }
                    return collect();
                }

                $locked->update(['status' => 'sending', 'started_at' => $locked->started_at ?? now()]);
                foreach ($rows as $row) {
                    $row->update(['status' => 'queued', 'released_at' => now()]);
                    $remaining--;
                }
                return $rows;
            });

            foreach ($released as $row) $jobs[] = $row->id;
        }

        foreach ($jobs as $recipientId) SendPromotionalEmailJob::dispatch($recipientId);
        $this->info('Released '.count($jobs).' promotional emails.');
        return self::SUCCESS;
    }
}
