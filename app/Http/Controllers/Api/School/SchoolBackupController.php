<?php

namespace App\Http\Controllers\Api\School;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\SchoolBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SchoolBackupController extends Controller
{
    public function download(Request $request, SchoolBackupService $backupService, AuditLogService $auditLogs)
    {
        $school = Auth::user()->school;
        abort_unless($school, 403, 'No active school context.');

        $path = $backupService->create($school);

        $auditLogs->record($request, 'backup_downloaded', 'Downloaded a school data backup archive.', [
            'school_id' => $school->id,
            'filename' => basename($path),
            'backup_type' => 'full_school',
        ]);

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, private',
        ])->deleteFileAfterSend(true);
    }

    public function downloadModule(Request $request, string $module, SchoolBackupService $backupService, AuditLogService $auditLogs)
    {
        $school = Auth::user()->school;
        abort_unless($school, 403, 'No active school context.');

        try {
            $path = $backupService->createModule($school, $module);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $auditLogs->record($request, 'export_downloaded', 'Downloaded a school module export.', [
            'school_id' => $school->id,
            'filename' => basename($path),
            'module' => strtolower($module),
        ]);

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, private',
        ])->deleteFileAfterSend(true);
    }
}
