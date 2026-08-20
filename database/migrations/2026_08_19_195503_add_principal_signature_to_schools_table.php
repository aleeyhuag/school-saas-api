<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 53 template redesign — one signature image per school, used
 * on every ID card generated for that school (not per individual
 * principal-user — a school's principal can change, but re-uploading
 * a new signature when that happens is simpler than trying to track
 * "whoever is principal right now" as a live binding).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('principal_signature_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('principal_signature_path');
        });
    }
};
