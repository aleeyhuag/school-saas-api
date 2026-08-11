<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('to_academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('from_school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('to_school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->string('action'); // promoted, repeated, skipped, transferred, withdrawn
            $table->text('note')->nullable();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'to_academic_session_id']);
            $table->index(['school_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_promotions');
    }
};
