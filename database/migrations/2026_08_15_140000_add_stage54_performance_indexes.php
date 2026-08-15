<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 54 — Scale & Performance.
 *
 * `->constrained()` adds a FOREIGN KEY constraint, but on PostgreSQL
 * (our production database, via Supabase) that does NOT automatically
 * create a secondary index on the referencing (child) column — unlike
 * MySQL/InnoDB, which does. Every one of these tables has been running
 * on the FK constraint alone with no supporting index, so filtering by
 * school_id/school_class_id/term_id/etc — which is what every list,
 * marksheet, attendance, fee, and report-card query does — has been a
 * sequential scan. This was invisible in local dev because SQLite
 * datasets there are tiny; it becomes very visible once schools carry
 * hundreds-to-thousands of students.
 *
 * Every index below was chosen to match a real filter combination
 * already used in a controller/service — not a blanket "index
 * everything" pass. See the inline comment on each for where it's used.
 */
return new class extends Migration
{
    public function up(): void
    {
        // StudentController::index() / myClass() — filters by school_id
        // and (optionally) school_class_id together.
        Schema::table('students', function (Blueprint $table) {
            $table->index(['school_id', 'school_class_id'], 'students_school_class_idx');
        });

        // ResultService::computeSubjectPositions() / SubjectScoreController
        // — filters by (school_class_id, subject_id, term_id). The existing
        // unique index leads with student_id, so it doesn't serve this
        // class-wide lookup.
        Schema::table('subject_scores', function (Blueprint $table) {
            $table->index(['school_class_id', 'subject_id', 'term_id'], 'subject_scores_class_subject_term_idx');
            // ResultService::computeOverallPosition() / classMarksheet() —
            // filters by (school_class_id, term_id) without a subject.
            $table->index(['school_class_id', 'term_id'], 'subject_scores_class_term_idx');
        });

        // AttendanceController class-register pulls and the daily
        // register view — filter by (school_class_id, date). The
        // existing unique index leads with student_id, not useful here.
        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['school_class_id', 'date'], 'attendances_class_date_idx');
            // SchoolHealthController-style school-wide attendance
            // aggregation — filters by (school_id, date).
            $table->index(['school_id', 'date'], 'attendances_school_date_idx');
        });

        // FeeService::studentFeeStatus() — filters by (student_id, status).
        Schema::table('fee_payments', function (Blueprint $table) {
            $table->index(['student_id', 'status'], 'fee_payments_student_status_idx');
            // FeeService::schoolFeeSummary() — sums by school_id.
            $table->index('school_id', 'fee_payments_school_idx');
        });

        // FeeService::schoolFeeSummary() / studentFeeStatus() — filters
        // by (school_id, term_id) and (term_id, school_class_id).
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->index(['school_id', 'term_id'], 'fee_structures_school_term_idx');
        });

        // AnnouncementController::index() — every announcements list
        // load does AnnouncementRead::where('user_id', ...). The existing
        // unique index leads with announcement_id, so a user_id-only
        // filter (the common case — one user, all their reads) doesn't
        // use it. This is one of the hottest queries in the app since it
        // runs on every dashboard/announcements page view.
        Schema::table('announcement_reads', function (Blueprint $table) {
            $table->index('user_id', 'announcement_reads_user_idx');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_school_class_idx');
        });

        Schema::table('subject_scores', function (Blueprint $table) {
            $table->dropIndex('subject_scores_class_subject_term_idx');
            $table->dropIndex('subject_scores_class_term_idx');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendances_class_date_idx');
            $table->dropIndex('attendances_school_date_idx');
        });

        Schema::table('fee_payments', function (Blueprint $table) {
            $table->dropIndex('fee_payments_student_status_idx');
            $table->dropIndex('fee_payments_school_idx');
        });

        Schema::table('fee_structures', function (Blueprint $table) {
            $table->dropIndex('fee_structures_school_term_idx');
        });

        Schema::table('announcement_reads', function (Blueprint $table) {
            $table->dropIndex('announcement_reads_user_idx');
        });
    }
};
