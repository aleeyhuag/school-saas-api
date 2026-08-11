<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Collapses the class_teacher/subject_teacher role split into one
 * unified `teacher` role. The distinction between "class teacher" and
 * "subject teacher" now lives ENTIRELY in the teacher_assignments
 * table (is_class_teacher / subject_id per row), which was already
 * the real source of truth for what a teacher can actually do —
 * having two separate Spatie roles on top of that was redundant and
 * caused a real problem: a teacher holding both roles needed manual
 * "+Role" management and got an asymmetric dual-dashboard experience
 * depending on which role they were granted first.
 *
 * Anyone currently holding class_teacher and/or subject_teacher keeps
 * full access — they're just given the single `teacher` role instead,
 * and the old role assignments are removed (the old Role rows
 * themselves are left in place, just unused, so nothing else breaks
 * if something still references them during the transition).
 */
return new class extends Migration
{
    public function up(): void
    {
        $teacherRole = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $oldRoles = Role::whereIn('name', ['class_teacher', 'subject_teacher'])->get();

        foreach ($oldRoles as $oldRole) {
            $userIds = DB::table('model_has_roles')
                ->where('role_id', $oldRole->id)
                ->where('model_type', \App\Models\User::class)
                ->pluck('model_id');

            foreach ($userIds as $userId) {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $teacherRole->id,
                    'model_type' => \App\Models\User::class,
                    'model_id' => $userId,
                ]);
            }

            DB::table('model_has_roles')->where('role_id', $oldRole->id)->delete();
        }
    }

    public function down(): void
    {
        // Intentionally no reverse migration — splitting `teacher`
        // back into class_teacher/subject_teacher would require
        // guessing which one each person originally had, which isn't
        // safely recoverable.
    }
};
