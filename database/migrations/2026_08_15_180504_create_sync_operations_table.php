<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 52 — Offline-First Foundation.
 *
 * Every offline-queued write (attendance mark, score save) that reaches
 * the server goes through this table first. It does two jobs at once:
 *
 *  1. IDEMPOTENCY — the client generates a UUID for each queued
 *     operation *before* it ever tries to send it. If a sync request
 *     succeeds but the response is lost (connection drops right as it
 *     comes back, browser tab closes mid-request), the client's retry
 *     carries the SAME uuid. `client_uuid` is unique, so a retry is
 *     recognized and the stored result is replayed instead of the
 *     write happening twice.
 *
 *  2. CONFLICT RECORD-KEEPING — `payload` (what was submitted) and
 *     `conflict_data` (what was already on the server, if different)
 *     are both stored, so a conflict can be reviewed and resolved
 *     later — including after a page reload, since this lives on the
 *     server rather than only in the browser's IndexedDB queue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // 'attendance_mark' | 'score_save'
            $table->timestamp('recorded_at'); // when the teacher actually took the action on their device, not when it reached the server
            $table->json('payload'); // exactly what was submitted
            $table->string('status'); // applied | conflict | failed | resolved
            $table->json('result')->nullable(); // what applying it produced (e.g. saved attendance rows, computed score)
            $table->json('conflict_data')->nullable(); // the server-side value(s) that disagreed, when status = conflict
            $table->string('resolution')->nullable(); // keep_local | keep_server, once a conflict is resolved
            $table->timestamps();

            $table->index(['school_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_operations');
    }
};
