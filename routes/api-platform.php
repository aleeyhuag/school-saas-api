<?php

use App\Http\Controllers\Api\Platform\PlatformSchoolController;
use App\Http\Controllers\Api\Platform\PlatformStatsController;
use App\Http\Controllers\Api\Platform\PlatformBackupController;
use App\Http\Controllers\Api\Platform\PlatformSuperAdminController;
use App\Http\Controllers\Api\Platform\PlatformLeadController;
use App\Http\Controllers\Api\Platform\PlatformCampaignController;
use App\Http\Controllers\Api\Platform\PlatformReferralController;
use App\Http\Controllers\Diagnostic\StorageHealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Platform Admin (super_admin only)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'role:super_admin'])->prefix('platform')->group(function () {
    Route::get('schools', [PlatformSchoolController::class, 'index']);
    Route::post('schools', [PlatformSchoolController::class, 'store']);
    Route::get('schools/{school}', [PlatformSchoolController::class, 'show']);
    Route::post('schools/{school}/toggle-active', [PlatformSchoolController::class, 'toggleActive']);
    Route::delete('schools/{school}', [PlatformSchoolController::class, 'destroy']);
    Route::get('stats', [PlatformStatsController::class, 'show']);
    Route::get('backup/download', [PlatformBackupController::class, 'download']);
    Route::get('schools/{school}/backup/download', [PlatformBackupController::class, 'downloadSchool']);
    Route::get('schools/{school}/export/{module}', [PlatformBackupController::class, 'downloadSchoolModule']);
    Route::get('diagnostics/storage-health', StorageHealthController::class);
    Route::get('super-admins', [PlatformSuperAdminController::class, 'index']);
    Route::post('super-admins', [PlatformSuperAdminController::class, 'store']);
    Route::post('super-admins/{user}/toggle-status', [PlatformSuperAdminController::class, 'toggleStatus']);
    Route::delete('super-admins/{user}', [PlatformSuperAdminController::class, 'destroy']);

    Route::get('leads', [PlatformLeadController::class, 'index']);
    Route::post('leads', [PlatformLeadController::class, 'store']);
    Route::put('leads/{lead}', [PlatformLeadController::class, 'update']);
    Route::delete('leads/{lead}', [PlatformLeadController::class, 'destroy']);
    Route::post('leads/import', [PlatformLeadController::class, 'import']);

    Route::get('campaigns', [PlatformCampaignController::class, 'index']);
    Route::post('campaigns', [PlatformCampaignController::class, 'store']);
    Route::get('campaigns/{emailCampaign}', [PlatformCampaignController::class, 'show']);
    Route::post('campaigns/{emailCampaign}/cancel', [PlatformCampaignController::class, 'cancel']);

    Route::get('referral-partners', [PlatformReferralController::class, 'index']);
    Route::post('referral-partners', [PlatformReferralController::class, 'store']);
    Route::get('referral-partners/{referralPartner}', [PlatformReferralController::class, 'show']);
    Route::put('referral-partners/{referralPartner}', [PlatformReferralController::class, 'update']);
    Route::post('referral-commissions/{referralCommission}/mark-paid', [PlatformReferralController::class, 'markCommissionPaid']);
    Route::get('referral-settings', [PlatformReferralController::class, 'getSettings']);
    Route::put('referral-settings', [PlatformReferralController::class, 'updateSettings']);
});
