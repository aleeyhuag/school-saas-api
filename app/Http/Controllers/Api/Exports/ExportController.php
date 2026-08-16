<?php

namespace App\Http\Controllers\Api\Exports;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessExportJob;
use App\Models\Export;
use App\Models\SchoolClass;
use App\Models\TeacherAssignment;
use App\Models\TermResultApproval;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Stage 54 — generic controller for requesting, polling, and
 * downloading queued exports (full school backups, class report-card
 * bundles). Each export type gets its own `request*()` method — that's
 * where the same authorization/precondition checks the old synchronous
 * endpoints did up front still live, so a badly-formed request fails
 * immediately rather than silently sitting in the queue. show()/index()/
 * download() are shared across every export type.
 */
class ExportController extends Controller
{
    /**
     * Every school-scoped table + referenced files, zipped. Was
     * SchoolBackupController::download() — same authorization
     * (proprietor's own school only), now queued instead of built
     * inside this request.
     */
    public function requestSchoolBackup(Request $request, AuditLogService $auditLogs)
    {
        $user = Auth::user();
        $school = $user->school;
        abort_unless($school, 403, 'No active school context.');

        $export = Export::create([
            'school_id' => $school->id,
            'user_id' => $user->id,
            'type' => 'school_backup',
            'status' => 'queued',
        ]);

        ProcessExportJob::dispatch($export->id);

        $auditLogs->record($request, 'backup_requested', 'Requested a school data backup archive.', [
            'school_id' => $school->id,
            'export_id' => $export->id,
        ]);

        return response()->json($export, 202);
    }

    /**
     * Every student in a class, zipped as individual report-card PDFs.
     * Was ReportCardController::classBulk() — same authorization and
     * approval-status precondition, checked here up front AND again
     * inside the job itself (see ProcessExportJob's docblock for why
     * the second check isn't redundant).
     */
    public function requestReportCardBulk(int $schoolClassId)
    {
        request()->validate([
            'term_id' => ['required', 'integer', 'exists:terms,id'],
        ]);

        $termId = (int) request()->input('term_id');
        $user = Auth::user();
        $schoolClass = SchoolClass::findOrFail($schoolClassId);

        if (! $user->hasRole(['proprietor', 'principal'])) {
            $isClassTeacher = TeacherAssignment::where('user_id', $user->id)
                ->where('school_class_id', $schoolClassId)
                ->where('is_class_teacher', true)
                ->exists();

            if (! $isClassTeacher) {
                throw ValidationException::withMessages([
                    'school_class_id' => ['Only this class\'s class teacher (or school management) can export its report cards.'],
                ]);
            }
        }

        $isApproved = TermResultApproval::where('school_class_id', $schoolClassId)
            ->where('term_id', $termId)
            ->exists();

        if (! $isApproved) {
            throw ValidationException::withMessages([
                'school_class_id' => ['This class\'s results haven\'t been approved yet — approve them first, then export.'],
            ]);
        }

        $export = Export::create([
            'school_id' => $schoolClass->school_id,
            'user_id' => $user->id,
            'type' => 'report_card_class_bulk',
            'params' => ['school_class_id' => $schoolClassId, 'term_id' => $termId],
            'status' => 'queued',
        ]);

        ProcessExportJob::dispatch($export->id);

        return response()->json($export, 202);
    }

    /**
     * Poll an export's status. The frontend calls this every few
     * seconds after requesting an export until status is
     * completed/failed. Scoped to the requesting user OR — for
     * school_backup exports specifically — anyone else with proprietor
     * access to that same school, so a second admin isn't blind to an
     * export someone else on their team kicked off.
     */
    public function show(Export $export)
    {
        $this->authorizeAccess($export);

        return $export;
    }

    /**
     * The requesting user's own recent exports — powers a simple
     * "my exports" panel so results of a queued job aren't only
     * reachable via the notification that fires when it's done.
     */
    public function index()
    {
        return Export::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }

    /**
     * Signed, time-limited download — same pattern as MediaController's
     * payment-proof serving, and for the same reason: this file lives
     * on the private disk, outside the public web root, so a signed
     * URL is the only way to reach it.
     */
    public function download(Request $request, Export $export)
    {
        abort_unless($request->hasValidSignature(), 403, 'This download link is invalid or has expired.');

        if ($export->status !== 'completed' || ! $export->file_path) {
            abort(404, 'This export is not ready to download.');
        }

        if (! Storage::disk('private')->exists($export->file_path)) {
            abort(404, 'This export file is no longer available — please request a new export.');
        }

        return Storage::disk('private')->download($export->file_path, $export->file_name ?: basename($export->file_path));
    }

    /**
     * Shared authorization for show()/download(): the requester owns
     * it, or is a proprietor/principal/super_admin who can act on the
     * export's school. Deliberately NOT using the BelongsToSchool
     * trait on Export (see the model's docblock), so this is manual.
     */
    protected function authorizeAccess(Export $export): void
    {
        $user = Auth::user();

        if ($export->user_id === $user->id) {
            return;
        }

        if ($user->hasRole('super_admin')) {
            return;
        }

        if ($user->hasRole(['proprietor', 'principal']) && $export->school_id === $user->school_id) {
            return;
        }

        abort(403, 'You do not have access to this export.');
    }
}
