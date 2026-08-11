<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row = "the class teacher has reviewed and approved this
        // class's results for this term — parents/students may now
        // see them." Absence of a row = still in draft, staff-only.
        Schema::create('term_result_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('approved_at');
            $table->timestamps();

            $table->unique(['school_class_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_result_approvals');
    }
};
