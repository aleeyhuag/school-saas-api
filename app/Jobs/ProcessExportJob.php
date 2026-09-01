<?php

namespace App\Jobs;

use App\Models\Export;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TermResultApproval;
use App\Notifications\ExportReadyNotification;
use App\Services\ReportCardPdfService;
use App\Services\SchoolBackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Builds a queued export (full school backup, class report-card bundle,
 * etc — see Export::$fillable's `type`) in the background instead of
 * inside an HTTP request. Everything this job does was previously done
 * synchronously by SchoolBackupController::download() / ReportCardController::classBulk();
 * this just moves the same work off the request thread and records
 * progress on the `exports` row so the frontend can poll it.
 *
 * Runs via `php artisan queue:work --stop-when-empty` on a Render Cron
 * Job (see render.yaml) rather than an always-on worker dyno — see the
 * Stage 54 README for why that's the right tradeoff for pilot scale.
 */
class ProcessExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // These build large files and can take real time; a blind
    // automatic retry would just repeat a slow failure. Better to
    // surface it as `failed` and let the person re-request explicitly.
    public int $tries = 1;

    public int $timeout = 600; // 10 minutes — generous for a 5,000-student backup

    public function __construct(protected int $exportId) {}

    public function handle(SchoolBackupService $backupService, ReportCardPdfService $pdfService): void
    {
        $export = Export::find($this->exportId);

        if (! $export || $export->status !== 'queued') {
            return; // already processed, or the row was removed in the meantime
        }

        $export->update(['status' => 'processing']);

        try {
            [$fullPath, $downloadName] = match ($export->type) {
                'school_backup' => $this->runSchoolBackup($export, $backupService),
                'report_card_class_bulk' => $this->runReportCardBulk($export, $pdfService),
                default => throw new \InvalidArgumentException("Unknown export type: {$export->type}"),
            };

            $relativePath = $this->uploadToPrivateDisk($export, $fullPath, $downloadName);

            $export->update([
                'status' => 'completed',
                'file_path' => $relativePath,
                'file_name' => $downloadName,
                'completed_at' => now(),
            ]);
        } catch (ValidationException $e) {
            // A precondition changed between request and processing
            // (e.g. results got un-approved) — this is an expected,
            // user-facing failure, not a bug. Store its message as-is.
            $export->update([
                'status' => 'failed',
                'error_message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Export job failed', [
                'export_id' => $export->id,
                'type' => $export->type,
                'error' => $e->getMessage(),
            ]);

            $export->update([
                'status' => 'failed',
                'error_message' => 'Something went wrong while building this export. Please try again, and contact support if it keeps happening.',
            ]);
        }

        // Deliberately outside the try/catch above and in its own
        // try/catch here — a notification failure (mail provider
        // rejecting the recipient, SMTP down, etc) must never crash
        // this request or mask a genuinely successful export. This was
        // a real bug: Resend's test/sandbox mode rejects mail to any
        // recipient other than the account owner, which was throwing
        // an uncaught TransportException here and turning a successful
        // export into a 500 response to the frontend — the export was
        // actually fine (already marked completed, file on disk above)
        // but the person never found out because the request itself
        // crashed on its way back.
        try {
            $export->user?->notify(new ExportReadyNotification($export->fresh()));
        } catch (\Throwable $e) {
            Log::warning('Export ready notification could not be sent', [
                'export_id' => $export->id,
                'user_id' => $export->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Both run*() methods build their ZIP against a real local path —
     * ZipArchive needs genuine file I/O, which an S3-backed disk can't
     * give it directly. This is the one place that bridges the two:
     * stream the finished local file into the 'private' Supabase disk
     * under a clean, predictable key, then remove the local scratch
     * copy. Streamed rather than read-then-put so a large school
     * backup doesn't have to fit entirely in memory.
     */
    protected function uploadToPrivateDisk(Export $export, string $localPath, string $downloadName): string
    {
        $relativePath = 'exports/'.$export->id.'/'.$downloadName;

        $stream = fopen($localPath, 'r');
        if ($stream === false) {
            throw new \RuntimeException('Unable to read the generated export file for upload.');
        }

        try {
            Storage::disk('private')->put($relativePath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        @unlink($localPath);

        return $relativePath;
    }

    protected function runSchoolBackup(Export $export, SchoolBackupService $backupService): array
    {
        $school = $export->school;

        if (! $school) {
            throw ValidationException::withMessages([
                'school_id' => ['This school no longer exists.'],
            ]);
        }

        $fullPath = $backupService->create($school);
        $downloadName = basename($fullPath);

        return [$fullPath, $downloadName];
    }

    /**
     * Re-runs the exact same authorization-relevant preconditions
     * ReportCardController::classBulk() checked at request time —
     * results-approval status in particular can change in the minutes
     * between a person requesting the export and a cron tick actually
     * processing it, so this isn't redundant, it's defense in depth
     * against a stale request.
     */
    protected function runReportCardBulk(Export $export, ReportCardPdfService $pdfService): array
    {
        $schoolClassId = (int) $export->params['school_class_id'];
        $termId = (int) $export->params['term_id'];

        $schoolClass = SchoolClass::findOrFail($schoolClassId);

        $isApproved = TermResultApproval::where('school_class_id', $schoolClassId)
            ->where('term_id', $termId)
            ->exists();

        if (! $isApproved) {
            throw ValidationException::withMessages([
                'school_class_id' => ['This class\'s results are no longer approved — approve them again, then re-request the export.'],
            ]);
        }

        $students = Student::where('school_class_id', $schoolClassId)->get();

        if ($students->isEmpty()) {
            throw ValidationException::withMessages([
                'school_class_id' => ['This class has no students.'],
            ]);
        }

        $tmpDir = storage_path('app/private/tmp-exports');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0750, true);
        }
        $fullPath = $tmpDir.'/'.uniqid('report-cards-class-'.$schoolClassId.'-', true).'.zip';

        $zip = new ZipArchive;
        $zip->open($fullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($students as $student) {
            $pdfOutput = $pdfService->build($student->id, $termId)->output();
            $zip->addFromString(str($student->full_name)->slug().'.pdf', $pdfOutput);
        }

        $zip->close();

        $downloadName = str($schoolClass->full_name)->slug().'-report-cards.zip';

        return [$fullPath, $downloadName];
    }

}
