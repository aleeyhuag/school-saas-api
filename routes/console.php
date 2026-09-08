<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Trial expiry, renewal grace-period enforcement, and trial-ending
// reminders — all three billing lifecycle checks in one daily pass.
Schedule::command('billing:process-lifecycle')->dailyAt('06:00');

// Marketing is deliberately processed synchronously in controlled batches.
// The project already treats database queue workers as non-guaranteed on the
// current Render setup; leaving released marketing rows in `queued` until a
// worker happens to run was the cause of campaigns remaining stuck.
Schedule::command('marketing:release-batch --batch=10')->everyFiveMinutes();

// Principal attendance alert after the normal school day. The scheduler
// runs in UTC on the backend, so explicitly use Lagos time here.
Schedule::command('attendance:notify-unmarked')->dailyAt('16:00')->timezone('Africa/Lagos');
