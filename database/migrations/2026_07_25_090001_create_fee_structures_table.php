<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Defines WHAT is owed: e.g. "Tuition - JSS1 - First Term = 45,000".
        // school_class_id is nullable so a fee can apply to a single class
        // OR to the whole school (e.g. a school-wide PTA levy).
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name'); // "Tuition", "PTA Levy", "Books", "Uniform"
            $table->decimal('amount', 10, 2);
            $table->date('due_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_structures');
    }
};
