<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Two-stage publish: approve() (existing) is the "temporary"
        // publish — visible to families, still editable, still
        // revocable. This adds the "permanent" stage on top of that
        // same row: once locked_at is set, scores for this class/term
        // can no longer be edited (SubjectScoreController checks this)
        // and the approval can no longer be revoked (ResultController
        // checks this) — closing the gap where an already-published,
        // already-seen result could be silently changed with no trace.
        Schema::table('term_result_approvals', function (Blueprint $table) {
            $table->timestamp('locked_at')->nullable()->after('approved_at');
            $table->foreignId('locked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('term_result_approvals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn('locked_at');
        });
    }
};
