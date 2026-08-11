<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete(); // null = whole-day attendance, set = period-specific
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marked_by')->constrained('users')->cascadeOnDelete(); // the teacher who marked it
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'late', 'excused'])->default('present');
            $table->timestamps();

            // one attendance record per student per class per subject per day
            $table->unique(['student_id', 'school_class_id', 'subject_id', 'date'], 'attendance_unique_entry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
