<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // the teacher

            // Day 1=Monday … 5=Friday; stored as integer so ordering
            // is trivial without mapping day names.
            $table->unsignedTinyInteger('day_of_week'); // 1-5

            // Period within the day (1, 2, 3 …) — simpler and more
            // flexible than storing raw clock times, since different
            // schools have different period lengths. The display label
            // (e.g. "Period 3: 10:00 – 10:40") is a presentation
            // concern, not a data concern.
            $table->unsignedTinyInteger('period');

            // Optional but useful — lets the timetable show actual
            // clock times even when start_time/end_time aren't stored
            // per-period in a separate table.
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->string('room')->nullable();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // A class can only have one subject per period per day.
            $table->unique(
                ['school_class_id', 'day_of_week', 'period', 'academic_session_id'],
                'timetable_class_period_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_timetable_entries');
    }
};
