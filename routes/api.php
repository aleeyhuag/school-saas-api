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
require __DIR__.'/api-media.php';

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
