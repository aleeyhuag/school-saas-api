<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Off by default — a school's public enrollment link only exists
     * once they deliberately turn it on in Settings.
     */
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->boolean('self_enrollment_enabled')->default(false)->after('admission_number_sequence');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('self_enrollment_enabled');
        });
    }
};
