<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class SchoolBackupService
{
    /**
     * Build a portable, school-scoped backup. Credentials, sessions,
     * personal access tokens, password reset tokens and platform cache/jobs
     * are intentionally excluded from a proprietor download.
     */
    public function create(School $school): string
    {
        $stamp = now()->format('Ymd_His');
        $base = storage_path('app/private/backups');
        $work = $base.'/school_'.$school->id.'_'.$stamp.'_'.Str::random(8);
        $zipPath = $base.'/eduventor_'.$school->slug.'_'.$stamp.'.zip';

        if (! is_dir($work)) {
            mkdir($work, 0750, true);
        }
        if (! is_dir($base)) {
            mkdir($base, 0750, true);
        }

        $this->writeJson($work.'/manifest.json', [
            'product' => 'EduVentor',
            'backup_type' => 'school_data_export',
            'generated_at' => now()->toIso8601String(),
            'school_id' => $school->id,
            'school_name' => $school->name,
            'note' => 'Credentials, access tokens, sessions, password-reset tokens, cache and queue data are excluded for security.',
        ]);

        $this->exportSchoolTables($school, $work.'/data');
        $this->exportReferencedFiles($school, $work.'/files');

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->deleteDirectory($work);
            throw new \RuntimeException('Unable to create the backup archive.');
        }

        $this->addDirectory($zip, $work, 'backup');
        $zip->close();
        $this->deleteDirectory($work);

        return $zipPath;
    }

    protected function exportSchoolTables(School $school, string $directory): void
    {
        if (! is_dir($directory)) mkdir($directory, 0750, true);

        $schoolTables = [
            'schools', 'users', 'academic_sessions', 'terms', 'school_classes', 'subjects',
            'students', 'teacher_assignments', 'attendances', 'assessment_settings',
            'grade_boundaries', 'subject_scores', 'fee_structures', 'fee_payments',
            'term_result_approvals', 'student_guardians', 'class_timetable_entries',
            'exam_timetable_entries', 'exam_timetable_classes', 'period_definitions',
            'announcements', 'announcement_reads', 'subscriptions', 'payments',
            'student_promotions', 'audit_logs', 'proprietor_school_access',
        ];

        $classIds = DB::table('school_classes')->where('school_id', $school->id)->pluck('id');
        $studentIds = DB::table('students')->where('school_id', $school->id)->pluck('id');
        $userIds = DB::table('users')->where('school_id', $school->id)->pluck('id');
        $announcementIds = DB::table('announcements')->where('school_id', $school->id)->pluck('id');
        $examIds = DB::table('exam_timetable_entries')->where('school_id', $school->id)->pluck('id');

        foreach ($schoolTables as $table) {
            if (! Schema::hasTable($table)) continue;

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
            }

            // Users are exported without credential/security secrets.
            if ($table === 'users') {
                $query->select(array_values(array_filter(Schema::getColumnListing('users'), fn ($c) => ! in_array($c, [
                    'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
                ], true))));
            }

            $rows = $query->get()->map(fn ($row) => (array) $row)->all();
            $this->writeJson($directory.'/'.$table.'.json', $rows);
        }

        // Class/subject membership has no school_id; scope it through classes.
        if (Schema::hasTable('class_subject')) {
            $this->writeJson($directory.'/class_subject.json', DB::table('class_subject')->whereIn('school_class_id', $classIds->all())->get()->map(fn ($r) => (array) $r)->all());
        }

        // Notifications are scoped by the users who belong to this school.
        if (Schema::hasTable('notifications')) {
            $this->writeJson($directory.'/notifications.json', DB::table('notifications')
                ->where('notifiable_type', \App\Models\User::class)
                ->whereIn('notifiable_id', $userIds->all())
                ->get()->map(fn ($r) => (array) $r)->all());
        }

        // Role assignments are useful for restoring school staff roles but
        // contain no passwords or tokens.
        $roleTable = config('permission.table_names.model_has_roles', 'model_has_roles');
        if (Schema::hasTable($roleTable)) {
            $this->writeJson($directory.'/'.$roleTable.'.json', DB::table($roleTable)
                ->where('model_type', \App\Models\User::class)
                ->whereIn('model_id', $userIds->all())
                ->get()->map(fn ($r) => (array) $r)->all());
        }
    }

    protected function exportReferencedFiles(School $school, string $directory): void
    {
        if (! is_dir($directory)) mkdir($directory, 0750, true);

        $public = Storage::disk('public');
        $paths = [];
        if ($school->logo_path) $paths[] = ['path' => $school->logo_path, 'name' => 'school-logo/'.basename($school->logo_path)];

        if (Schema::hasTable('payments')) {
            $proofs = DB::table('payments')->where('school_id', $school->id)->whereNotNull('proof_path')->pluck('proof_path');
            foreach ($proofs as $path) {
                $paths[] = ['path' => $path, 'name' => 'payment-proofs/'.basename($path)];
            }
        }

        foreach ($paths as $item) {
            if (! $public->exists($item['path'])) continue;
            $target = $directory.'/'.$item['name'];
            $targetDir = dirname($target);
            if (! is_dir($targetDir)) mkdir($targetDir, 0750, true);
            file_put_contents($target, $public->get($item['path']));
        }
    }

    protected function writeJson(string $path, array $data): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) mkdir($dir, 0750, true);
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function addDirectory(ZipArchive $zip, string $directory, string $prefix): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($items as $file) {
            if (! $file->isFile()) continue;
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($directory) + 1));
            $zip->addFile($file->getPathname(), trim($prefix.'/'.$relative, '/'));
        }
    }

    protected function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) return;
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($directory);
    }
}
