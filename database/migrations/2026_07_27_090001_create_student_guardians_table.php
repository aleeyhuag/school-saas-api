<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Links a parent's login account (User with role 'parent') to
        // the Student record(s) they're guardian of. A pivot table
        // rather than a single column on students, since a student
        // can have more than one guardian (both parents wanting
        // access) and one guardian can have more than one child
        // (siblings) at the same school.
        Schema::create('student_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_guardians');
    }
};
