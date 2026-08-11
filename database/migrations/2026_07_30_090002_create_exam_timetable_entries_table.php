<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();

            // Unlike class timetable, exam timetable uses actual
            // calendar dates (not day-of-week + session) because
            // exams happen on specific fixed dates, not weekly.
            $table->date('exam_date');
            $table->time('start_time');
            $table->time('end_time');

            // Which classes sit this exam (a subject exam might be
            // for one class or several). Many-to-many via pivot.
            $table->string('venue')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Same subject can't be scheduled twice on the same day
            // for the same term.
            $table->unique(['term_id', 'subject_id', 'exam_date'], 'exam_timetable_unique');
        });

        // Which classes sit each exam entry
        Schema::create('exam_timetable_classes', function (Blueprint $table) {
            $table->foreignId('exam_timetable_entry_id')
                ->constrained('exam_timetable_entries')
                ->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->primary(['exam_timetable_entry_id', 'school_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_timetable_classes');
        Schema::dropIfExists('exam_timetable_entries');
    }
};
