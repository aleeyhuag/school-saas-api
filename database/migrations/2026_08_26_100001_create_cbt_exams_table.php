<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->unsignedInteger('duration_minutes');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->decimal('pass_mark', 5, 2)->default(50);
            $table->boolean('randomize_questions')->default(false);
            $table->boolean('randomize_options')->default(false);
            $table->boolean('published')->default(false);
            $table->timestamps();
            $table->index(['school_id', 'published', 'starts_at']);
        });

        Schema::create('cbt_exam_classes', function (Blueprint $table) {
            $table->foreignId('cbt_exam_id')->constrained('cbt_exams')->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->primary(['cbt_exam_id', 'school_class_id']);
        });

        Schema::create('cbt_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cbt_exam_id')->constrained('cbt_exams')->cascadeOnDelete();
            $table->text('question_text');
            $table->string('topic')->nullable();
            $table->decimal('marks', 6, 2)->default(1);
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
        });

        Schema::create('cbt_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cbt_question_id')->constrained('cbt_questions')->cascadeOnDelete();
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
        });

        Schema::create('cbt_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cbt_exam_id')->constrained('cbt_exams')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('expires_at');
            $table->dateTime('submitted_at')->nullable();
            $table->string('status')->default('in_progress');
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('percentage', 6, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->unsignedInteger('attempted_count')->default(0);
            $table->unsignedInteger('unanswered_count')->default(0);
            $table->unsignedInteger('time_used_seconds')->nullable();
            $table->timestamps();
            $table->unique(['cbt_exam_id', 'student_id']);
            $table->index(['school_id', 'student_id', 'status']);
        });

        Schema::create('cbt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cbt_attempt_id')->constrained('cbt_attempts')->cascadeOnDelete();
            $table->foreignId('cbt_question_id')->constrained('cbt_questions')->cascadeOnDelete();
            $table->foreignId('cbt_question_option_id')->nullable()->constrained('cbt_question_options')->nullOnDelete();
            $table->timestamps();
            $table->unique(['cbt_attempt_id', 'cbt_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_answers');
        Schema::dropIfExists('cbt_attempts');
        Schema::dropIfExists('cbt_question_options');
        Schema::dropIfExists('cbt_questions');
        Schema::dropIfExists('cbt_exam_classes');
        Schema::dropIfExists('cbt_exams');
    }
};
