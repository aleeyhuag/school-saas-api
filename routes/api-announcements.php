<?php

use App\Http\Controllers\Api\Announcements\AnnouncementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Announcements
|--------------------------------------------------------------------------
|
| Everyone reads their own feed (audience filtering happens inside
| the controller via Announcement::visibleTo(), not at the route
| level — a parent and a proprietor hit the same index() endpoint and
| get different results). Posting is restricted to Proprietor,
| Principal, Bursar, Exam Officer, and Teacher (class teacher only,
| enforced inside store()) — Student and Parent never post.
|
| Reading is deliberately NOT gated by 'school.active' — same reason
| as api-notifications.php: DashboardLayout fetches this on every
| page (for the sidebar's unread badge), including the Billing page a
| locked-out Proprietor needs to reach. Posting stays gated, since
| that's a real write action, not something the billing exception
| needs to cover.
|
*/

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('announcements', [AnnouncementController::class, 'index']);
    Route::post('announcements/{announcement}/read', [AnnouncementController::class, 'markRead']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|bursar|exam_officer|teacher'])->group(function () {
    Route::get('announcements/compose-options', [AnnouncementController::class, 'composeOptions']);
    Route::post('announcements', [AnnouncementController::class, 'store']);
});
