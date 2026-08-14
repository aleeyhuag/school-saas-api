<?php

namespace App\Services;

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class SchoolBackupService
{
    /**
     * Build a portable, school-scoped recovery archive.
     *
     * Security-sensitive credentials/tokens are deliberately excluded.
     * Large tables are streamed into JSON arrays instead of being loaded
     * into one enormous PHP array, so the export remains practical for
     * schools with hundreds/thousands of students.
     */
    public function create(School $school): string
    {
        $stamp = now()->format('Ymd_His');
        $base = storage_path('app/private/backups');
        $work = $base.'/school_'.$school->id.'_'.$stamp.'_'.Str::random(8);
        $zipPath = $base.'/skulag_'.$school->slug.'_'.$stamp.'.zip';

        $this->prepareDirectory($work.'/data');
        $this->prepareDirectory($work.'/files');

        $this->writeJsonFile($work.'/manifest.json', $this->schoolManifest($school, 'school_data_backup'));
        $this->exportSchoolTables($school, $work.'/data');
        $this->exportReferencedFiles($school, $work.'/files');

        return $this->zipAndCleanup($work, $zipPath, 'Unable to create the school backup archive.');
    }

    /**
     * Create a focused module export. This is still a ZIP so every export
     * has a manifest and a predictable data/ directory.
     */
    public function createModule(School $school, string $module): string
    {
        $module = strtolower(trim($module));
        $allowed = [
            'students', 'staff', 'classes', 'attendance', 'results',
            'payments', 'fees', 'audit', 'timetable', 'announcements',
        ];

        if (! in_array($module, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported school export module.');
        }

        $stamp = now()->format('Ymd_His');
        $base = storage_path('app/private/backups');
        $work = $base.'/module_'.$module.'_school_'.$school->id.'_'.$stamp.'_'.Str::random(8);
        $zipPath = $base.'/skulag_'.$school->slug.'_'.$module.'_'.$stamp.'.zip';

        $this->prepareDirectory($work.'/data');

        $this->writeJsonFile($work.'/manifest.json', array_merge(
            $this->schoolManifest($school, 'module_export'),
            ['module' => $module]
        ));

        $this->exportModule($school, $module, $work.'/data');

        return $this->zipAndCleanup($work, $zipPath, 'Unable to create the module export archive.');
    }

    /**
     * Platform-wide recovery archive for Super Admin.
     */
    public function createPlatform(): string
    {
        $stamp = now()->format('Ymd_His');
        $base = storage_path('app/private/backups');
        $work = $base.'/platform_'.$stamp.'_'.Str::random(8);
        $zipPath = $base.'/skulag_platform_'.$stamp.'.zip';

        $this->prepareDirectory($work.'/data');
        $this->prepareDirectory($work.'/files');

        $this->writeJsonFile($work.'/manifest.json', [
            'product' => 'Skulag',
            'parent_company' => 'AG Komputech',
            'backup_type' => 'platform_data_backup',
            'generated_at' => now()->toIso8601String(),
            'application_version' => config('app.version', env('APP_VERSION', 'unknown')),
            'schema_version' => $this->schemaVersion(),
            'note' => 'Credentials, access tokens, sessions, password-reset tokens, cache and queue data are excluded for security.',
        ]);

        $excludedTables = [
            'password_reset_tokens', 'personal_access_tokens', 'sessions',
            'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
            'migrations', 'sqlite_sequence',
        ];

        foreach (Schema::getTableListing() as $table) {
            if (in_array($table, $excludedTables, true) || ! Schema::hasTable($table)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);
            $select = $this->safeColumns($columns);
            if ($select === []) {
                continue;
            }

            $this->streamQueryToJson(
                DB::table($table)->select($select),
                $work.'/data/'.$table.'.json'
            );
        }

        $this->exportPlatformFiles($work.'/files');

        return $this->zipAndCleanup($work, $zipPath, 'Unable to create the platform backup archive.');
    }

    protected function schoolManifest(School $school, string $backupType): array
    {
        return [
            'product' => 'Skulag',
            'parent_company' => 'AG Komputech',
            'backup_type' => $backupType,
            'generated_at' => now()->toIso8601String(),
            'school_id' => $school->id,
            'school_name' => $school->name,
            'generated_by_user_id' => auth()->id(),
            'application_version' => config('app.version', env('APP_VERSION', 'unknown')),
            'schema_version' => $this->schemaVersion(),
            'contents' => [
                'database_records' => true,
                'referenced_files' => true,
                'credentials' => false,
                'sessions_and_tokens' => false,
            ],
            'note' => 'Credentials, access tokens, sessions, password-reset tokens, cache and queue data are excluded for security.',
        ];
    }

    protected function exportSchoolTables(School $school, string $directory): void
    {
        $this->prepareDirectory($directory);

        $classIds = DB::table('school_classes')->where('school_id', $school->id)->pluck('id');
        $studentIds = DB::table('students')->where('school_id', $school->id)->pluck('id');
        $userIds = DB::table('users')->where('school_id', $school->id)->pluck('id');
        $announcementIds = DB::table('announcements')->where('school_id', $school->id)->pluck('id');
        $examIds = DB::table('exam_timetable_entries')->where('school_id', $school->id)->pluck('id');
        $paymentIds = DB::table('payments')->where('school_id', $school->id)->pluck('id');
        $planIds = DB::table('payments')->where('school_id', $school->id)->pluck('plan_id')
            ->merge(DB::table('subscriptions')->where('school_id', $school->id)->pluck('plan_id'))
            ->filter()->unique()->values();

        $schoolTables = [
            'schools', 'users', 'academic_sessions', 'terms', 'school_classes', 'subjects',
            'students', 'teacher_assignments', 'attendances', 'assessment_settings',
            'grade_boundaries', 'subject_scores', 'fee_structures', 'fee_payments',
            'term_result_approvals', 'student_guardians', 'class_timetable_entries',
            'exam_timetable_entries', 'period_definitions', 'announcements',
            'announcement_reads', 'subscriptions', 'payments', 'payment_reviews',
            'student_promotions', 'audit_logs', 'proprietor_school_access',
        ];

        foreach ($schoolTables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $query = DB::table($table);

            if ($table === 'schools') {
                $query->where('id', $school->id);
            } elseif (Schema::hasColumn($table, 'school_id')) {
                $query->where($table.'.school_id', $school->id);
            } elseif ($table === 'class_subject') {
                $query->whereIn('school_class_id', $classIds->all());
            } elseif ($table === 'exam_timetable_classes') {
                $query->whereIn('exam_timetable_entry_id', $examIds->all());
            } elseif ($table === 'announcement_reads') {
                $query->whereIn('announcement_id', $announcementIds->all());
            } elseif ($table === 'payment_reviews') {
                $query->whereIn('payment_id', $paymentIds->all());
            }

            if ($table === 'users') {
                $query->select($this->safeColumns(Schema::getColumnListing('users')));
            } elseif ($table === 'payment_reviews') {
                $query->whereIn('payment_id', $paymentIds->all());
            }

            $this->streamQueryToJson($query, $directory.'/'.$table.'.json');
        }

        if (Schema::hasTable('class_subject')) {
            $this->streamQueryToJson(
                DB::table('class_subject')->whereIn('school_class_id', $classIds->all()),
                $directory.'/class_subject.json'
            );
        }

        if (Schema::hasTable('exam_timetable_classes')) {
            $this->streamQueryToJson(
                DB::table('exam_timetable_classes')->whereIn('exam_timetable_entry_id', $examIds->all()),
                $directory.'/exam_timetable_classes.json'
            );
        }

        if (Schema::hasTable('notifications')) {
            $this->streamQueryToJson(
                DB::table('notifications')
                    ->where('notifiable_type', User::class)
                    ->whereIn('notifiable_id', $userIds->all()),
                $directory.'/notifications.json'
            );
        }

        $roleTable = config('permission.table_names.model_has_roles', 'model_has_roles');
        if (Schema::hasTable($roleTable)) {
            $this->streamQueryToJson(
                DB::table($roleTable)
                    ->where('model_type', User::class)
                    ->whereIn('model_id', $userIds->all()),
                $directory.'/'.$roleTable.'.json'
            );
        }

        // Plans are platform-level records, but these are the exact plan
        // definitions referenced by this school's subscription/payments.
        if (Schema::hasTable('plans') && $planIds->isNotEmpty()) {
            $this->streamQueryToJson(
                DB::table('plans')->whereIn('id', $planIds->all()),
                $directory.'/plans.json'
            );
        }
    }

    protected function exportModule(School $school, string $module, string $directory): void
    {
        switch ($module) {
            case 'students':
                $this->streamQueryToJson(DB::table('students')->where('school_id', $school->id), $directory.'/students.json');
                break;

            case 'staff':
                $userIds = DB::table('users')->where('school_id', $school->id)->pluck('id');
                $this->streamQueryToJson(DB::table('users')->where('school_id', $school->id)->select($this->safeColumns(Schema::getColumnListing('users'))), $directory.'/staff.json');
                $roleTable = config('permission.table_names.model_has_roles', 'model_has_roles');
                if (Schema::hasTable($roleTable)) {
                    $this->streamQueryToJson(DB::table($roleTable)->where('model_type', User::class)->whereIn('model_id', $userIds->all()), $directory.'/'.$roleTable.'.json');
                }
                break;

            case 'classes':
                $classIds = DB::table('school_classes')->where('school_id', $school->id)->pluck('id');
                $this->streamQueryToJson(DB::table('school_classes')->where('school_id', $school->id), $directory.'/classes.json');
                $this->streamQueryToJson(DB::table('class_subject')->whereIn('school_class_id', $classIds->all()), $directory.'/class_subject.json');
                break;

            case 'attendance':
                $this->streamQueryToJson(DB::table('attendances')->where('school_id', $school->id), $directory.'/attendance.json');
                break;

            case 'results':
                $this->streamQueryToJson(DB::table('subject_scores')->where('school_id', $school->id), $directory.'/subject_scores.json');
                $this->streamQueryToJson(DB::table('term_result_approvals')->where('school_id', $school->id), $directory.'/term_result_approvals.json');
                break;

            case 'payments':
                $paymentIds = DB::table('payments')->where('school_id', $school->id)->pluck('id');
                $this->streamQueryToJson(DB::table('payments')->where('school_id', $school->id), $directory.'/payments.json');
                if (Schema::hasTable('payment_reviews')) {
                    $this->streamQueryToJson(DB::table('payment_reviews')->whereIn('payment_id', $paymentIds->all()), $directory.'/payment_reviews.json');
                }
                $this->streamQueryToJson(DB::table('subscriptions')->where('school_id', $school->id), $directory.'/subscriptions.json');
                break;

            case 'fees':
                $this->streamQueryToJson(DB::table('fee_structures')->where('school_id', $school->id), $directory.'/fee_structures.json');
                $this->streamQueryToJson(DB::table('fee_payments')->where('school_id', $school->id), $directory.'/fee_payments.json');
                break;

            case 'audit':
                $this->streamQueryToJson(DB::table('audit_logs')->where('school_id', $school->id)->orderBy('id'), $directory.'/audit_logs.json');
                break;

            case 'timetable':
                $this->streamQueryToJson(DB::table('class_timetable_entries')->where('school_id', $school->id), $directory.'/class_timetable_entries.json');
                $examIds = DB::table('exam_timetable_entries')->where('school_id', $school->id)->pluck('id');
                $this->streamQueryToJson(DB::table('exam_timetable_entries')->where('school_id', $school->id), $directory.'/exam_timetable_entries.json');
                $this->streamQueryToJson(DB::table('exam_timetable_classes')->whereIn('exam_timetable_entry_id', $examIds->all()), $directory.'/exam_timetable_classes.json');
                $this->streamQueryToJson(DB::table('period_definitions')->where('school_id', $school->id), $directory.'/period_definitions.json');
                break;

            case 'announcements':
                $announcementIds = DB::table('announcements')->where('school_id', $school->id)->pluck('id');
                $this->streamQueryToJson(DB::table('announcements')->where('school_id', $school->id), $directory.'/announcements.json');
                $this->streamQueryToJson(DB::table('announcement_reads')->whereIn('announcement_id', $announcementIds->all()), $directory.'/announcement_reads.json');
                break;
        }
    }

    protected function exportReferencedFiles(School $school, string $directory): void
    {
        $this->prepareDirectory($directory);
        $paths = [];

        if ($school->logo_path) {
            $paths[] = [
                'path' => $school->logo_path,
                'name' => 'school-logo/school-'.$school->id.'-'.basename($school->logo_path),
            ];
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'proof_path')) {
            DB::table('payments')
                ->where('school_id', $school->id)
                ->whereNotNull('proof_path')
                ->get(['id', 'proof_path'])
                ->each(function ($payment) use (&$paths) {
                    $paths[] = [
                        'path' => $payment->proof_path,
                        'name' => 'payment-proofs/payment-'.$payment->id.'-'.basename($payment->proof_path),
                    ];
                });
        }

        $this->copyFiles($paths, $directory);
    }

    protected function exportPlatformFiles(string $directory): void
    {
        $this->prepareDirectory($directory);
        $paths = [];

        if (Schema::hasTable('schools') && Schema::hasColumn('schools', 'logo_path')) {
            DB::table('schools')->whereNotNull('logo_path')->get(['id', 'logo_path'])->each(function ($school) use (&$paths) {
                $paths[] = [
                    'path' => $school->logo_path,
                    'name' => 'school-logos/school-'.$school->id.'-'.basename($school->logo_path),
                ];
            });
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'proof_path')) {
            DB::table('payments')->whereNotNull('proof_path')->get(['id', 'proof_path'])->each(function ($payment) use (&$paths) {
                $paths[] = [
                    'path' => $payment->proof_path,
                    'name' => 'payment-proofs/payment-'.$payment->id.'-'.basename($payment->proof_path),
                ];
            });
        }

        $this->copyFiles($paths, $directory);
    }

    protected function copyFiles(array $paths, string $directory): void
    {
        $private = Storage::disk('private');
        $public = Storage::disk('public');

        foreach ($paths as $item) {
            $disk = $private->exists($item['path']) ? $private : $public;
            if (! $disk->exists($item['path'])) {
                continue;
            }

            $target = $directory.'/'.$item['name'];
            $this->prepareDirectory(dirname($target));
            file_put_contents($target, $disk->get($item['path']));
        }
    }

    protected function safeColumns(array $columns): array
    {
        $sensitiveColumns = [
            'password', 'remember_token', 'two_factor_secret',
            'two_factor_recovery_codes', 'secret', 'api_key',
            'access_token', 'refresh_token', 'token',
        ];

        return array_values(array_filter(
            $columns,
            fn ($column) => ! in_array($column, $sensitiveColumns, true)
        ));
    }

    protected function streamQueryToJson($query, string $path): void
    {
        $this->prepareDirectory(dirname($path));
        $handle = fopen($path, 'wb');
        if (! $handle) {
            throw new \RuntimeException('Unable to write backup data file: '.basename($path));
        }

        fwrite($handle, "[\n");
        $first = true;

        foreach ($query->cursor() as $row) {
            if (! $first) {
                fwrite($handle, ",\n");
            }
            fwrite($handle, json_encode((array) $row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $first = false;
        }

        fwrite($handle, "\n]\n");
        fclose($handle);
    }

    protected function writeJsonFile(string $path, array $data): void
    {
        $this->prepareDirectory(dirname($path));
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    protected function schemaVersion(): string
    {
        if (! Schema::hasTable('migrations')) {
            return 'unknown';
        }

        return (string) (DB::table('migrations')->orderByDesc('id')->value('migration') ?? 'unknown');
    }

    protected function prepareDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }
    }

    protected function zipAndCleanup(string $work, string $zipPath, string $errorMessage): string
    {
        $this->prepareDirectory(dirname($zipPath));

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->deleteDirectory($work);
            throw new \RuntimeException($errorMessage);
        }

        $this->addDirectory($zip, $work, 'backup');
        $zip->close();
        $this->deleteDirectory($work);

        return $zipPath;
    }

    protected function addDirectory(ZipArchive $zip, string $directory, string $prefix): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($items as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($directory) + 1));
            $zip->addFile($file->getPathname(), trim($prefix.'/'.$relative, '/'));
        }
    }

    protected function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }

        @rmdir($directory);
    }
}
