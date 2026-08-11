<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per student, per subject, per term. Teachers only
        // ever enter the raw score + the max it was out of for each
        // component — all weighting/grading/ranking is computed on
        // the fly (see App\Services\ResultService), never stored here,
        // so a change to weights or grade boundaries retroactively
        // recomputes correctly instead of leaving stale numbers behind.
        Schema::create('subject_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            $table->decimal('ca_score', 5, 2)->nullable();
            $table->decimal('ca_max', 5, 2)->default(20);

            $table->decimal('assignment_score', 5, 2)->nullable();
            $table->decimal('assignment_max', 5, 2)->default(10);

            $table->decimal('exam_score', 5, 2)->nullable();
            $table->decimal('exam_max', 5, 2)->default(100);

            $table->string('teacher_comment')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['student_id', 'subject_id', 'term_id'], 'subject_score_unique_entry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_scores');
    }
};
