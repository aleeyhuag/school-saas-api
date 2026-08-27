<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_question_banks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('question_text');
            $table->string('topic')->nullable();
            $table->decimal('marks', 6, 2)->default(1);
            $table->timestamps();
            $table->index(['school_id', 'subject_id']);
        });

        Schema::create('cbt_question_bank_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cbt_question_bank_id')->constrained('cbt_question_banks')->cascadeOnDelete();
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_question_bank_options');
        Schema::dropIfExists('cbt_question_banks');
    }
};
