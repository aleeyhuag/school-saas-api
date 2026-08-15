<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Services\SchoolBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlatformBackupController extends Controller
{
    public function download(Request $request, SchoolBackupService $backupService)
    {
        $path = $backupService->createPlatform();

        Log::info('Skulag platform backup downloaded.', [
            'user_id' => $request->user()?->id,
            'filename' => basename($path),
            'ip' => $request->ip(),
        ]);

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, private',
        ])->deleteFileAfterSend(true);
    }

    public function downloadSchool(Request $request, School $school, SchoolBackupService $backupService)
    {
        $path = $backupService->create($school);

        Log::info('Skulag school backup downloaded by Super Admin.', [
            'user_id' => $request->user()?->id,
            'school_id' => $school->id,
            'filename' => basename($path),
            'ip' => $request->ip(),
        ]);

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, private',
        ])->deleteFileAfterSend(true);
    }

    public function downloadSchoolModule(Request $request, School $school, string $module, SchoolBackupService $backupService)
    {
        try {
            $path = $backupService->createModule($school, $module);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        Log::info('Skulag school module export downloaded by Super Admin.', [
            'user_id' => $request->user()?->id,
            'school_id' => $school->id,
            'module' => $module,
            'filename' => basename($path),
            'ip' => $request->ip(),
        ]);

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, private',
        ])->deleteFileAfterSend(true);
    }
}
