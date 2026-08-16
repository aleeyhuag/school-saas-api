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

// Stage 54 — processes queued exports (school backups, class report
// card bundles) without needing an always-on worker dyno. Render's
// Cron Job service (see render.yaml) hits `schedule:run` every 5
// minutes; this line is what that tick actually executes. Any export
// requested in between sits as `queued` for up to ~5 minutes before
// this runs — acceptable latency for a background download, and this
// can be swapped for an always-on `queue:work` worker service later
// with no code change if that latency ever becomes a real complaint.
// --stop-when-empty exits as soon as the queue is drained rather than
// idling, and --max-time caps a single run well under Render Cron
// Job's execution ceiling.
Schedule::command('queue:work --stop-when-empty --max-time=250 --tries=1')->everyFiveMinutes();
