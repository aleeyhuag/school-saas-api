<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    /**
     * Creates the standard set of roles used across the platform.
     * Run with: php artisan db:seed --class=RolesSeeder
     *
     * NOTE: 'class_teacher' and 'subject_teacher' were unified into a
     * single 'teacher' role — see the
     * 2026_07_29_090001_unify_teacher_roles migration. The distinction
     * between class-teacher and subject-teacher duties now lives
     * entirely in the teacher_assignments table, not in the role
     * itself.
     */
    public function run(): void
    {
        $roles = [
            'super_admin',       // platform owner (you)
            'proprietor',        // school owner
            'principal',         // head teacher
            'bursar',            // accountant
            'exam_officer',
            'teacher',
            'student',
            'parent',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}
