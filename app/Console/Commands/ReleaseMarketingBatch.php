<?php

namespace App\Console\Commands;

use App\Services\MarketingCampaignDispatcher;
use Illuminate\Console\Command;

class ReleaseMarketingBatch extends Command
{
    protected $signature = 'marketing:release-batch {--batch=10}';
    protected $description = 'Send a controlled global batch of promotional emails.';

    public function handle(MarketingCampaignDispatcher $dispatcher): int
    {
        $count = $dispatcher->release((int) $this->option('batch'));
        $this->info("Processed {$count} promotional email(s).");
        return self::SUCCESS;
    }
}
