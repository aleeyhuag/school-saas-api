<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 54 — async export infrastructure.
 *
 * Backs the queued-job pattern for anything that builds a large ZIP/PDF
 * synchronously today (full school backups, class-wide report card
 * bundles, and — from Stage 53 onward — bulk ID card ZIPs). Rather than
 * building a bespoke table per export type, this is one generic
 * "export request" record: what was asked for (`type` + `params`),
 * who asked, and where the finished file ended up.
 *
 * `school_id` is nullable because Super Admin platform-wide exports
 * have no single owning school — same reasoning as `users.school_id`
 * being nullable for the same role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // e.g. 'school_backup', 'report_card_class_bulk'
            $table->json('params')->nullable(); // e.g. {"school_class_id": 4, "term_id": 9}
            $table->string('status')->default('queued'); // queued|processing|completed|failed
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['school_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
