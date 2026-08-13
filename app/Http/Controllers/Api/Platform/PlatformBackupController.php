<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Services\SchoolBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlatformBackupController extends Controller
{
    public function download(Request $request, SchoolBackupService $backupService)
    {
        $path = $backupService->createPlatform();

        Log::info('EduVentor platform backup downloaded.', [
            'user_id' => $request->user()?->id,
            'filename' => basename($path),
            'ip' => $request->ip(),
        ]);

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, private',
        ])->deleteFileAfterSend(true);
    }
}
