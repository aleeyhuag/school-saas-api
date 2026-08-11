<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // e.g. 70-100 = A (Excellent), 60-69 = B (Very Good), etc.
        // Each school configures its own boundaries once per session.
        Schema::create('grade_boundaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('grade', 5);             // "A", "B", "C1", etc.
            $table->string('remark')->nullable();     // "Excellent", "Good"
            $table->unsignedTinyInteger('min_score'); // inclusive
            $table->unsignedTinyInteger('max_score'); // inclusive
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_boundaries');
    }
};
