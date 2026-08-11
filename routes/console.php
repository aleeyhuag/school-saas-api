<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Trial expiry, renewal grace-period enforcement, and trial-ending
// reminders — all three billing lifecycle checks in one daily pass.
// Requires the Laravel scheduler to actually be running — see the
// Stage 39 README's install steps for the cron entry.
Schedule::command('billing:process-lifecycle')->dailyAt('06:00');
