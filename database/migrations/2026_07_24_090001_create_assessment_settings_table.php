<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per school: the weighting used to compute every
        // student's total score. Kept deliberately simple (school-wide,
        // not per-subject) in line with the "simplified system" goal —
        // can be made per-subject later without breaking this table.
        Schema::create('assessment_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('ca_weight')->default(30);         // out of 100
            $table->unsignedTinyInteger('assignment_weight')->default(10); // out of 100
            $table->unsignedTinyInteger('exam_weight')->default(60);       // out of 100
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_settings');
    }
};
