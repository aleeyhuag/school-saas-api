<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('posted_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            // whole_school: every actor. staff_only: proprietor,
            // principal, bursar, exam_officer, teacher — excludes
            // parent/student. class: one class's students (+ their
            // guardians if include_parents), posted by that class's
            // class teacher only.
            $table->enum('audience', ['whole_school', 'staff_only', 'class']);
            $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('include_parents')->default(false);
            $table->boolean('notify_by_email')->default(false);
            $table->timestamps();

            $table->index(['school_id', 'audience']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
