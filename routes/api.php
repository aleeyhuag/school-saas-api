<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

require __DIR__.'/api-auth.php';
require __DIR__.'/api-academic.php';
require __DIR__.'/api-attendance.php';
require __DIR__.'/api-sessions.php';
require __DIR__.'/api-grading.php';
require __DIR__.'/api-fees.php';
require __DIR__.'/api-platform.php';
require __DIR__.'/api-family.php';
require __DIR__.'/api-school.php';
require __DIR__.'/api-timetable.php';
require __DIR__.'/api-reports.php';
require __DIR__.'/api-notifications.php';
require __DIR__.'/api-announcements.php';
require __DIR__.'/api-branches.php';
require __DIR__.'/api-billing.php';
require __DIR__.'/api-unsubscribe.php';
require __DIR__.'/api-enrollment.php';
require __DIR__.'/api-referrals.php';
require __DIR__.'/api-media.php';
require __DIR__.'/api-governance.php';
require __DIR__.'/api-exports.php';
require __DIR__.'/api-sync.php';
require __DIR__.'/api-id-cards.php';
require __DIR__.'/api-cbt.php';
require __DIR__.'/api-ai.php';

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
