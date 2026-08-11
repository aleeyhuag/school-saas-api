<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Defines what "Period N" actually means for a school — its start
     * and end time. Every school runs a different bell schedule, so
     * this is set per-school rather than assumed platform-wide.
     */
    public function up(): void
    {
        Schema::create('period_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('period_number');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->unique(['school_id', 'period_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_definitions');
    }
};
