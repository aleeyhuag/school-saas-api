<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets each school choose 1, 2 or 3 continuous assessments (CAs).
     *
     * Existing schools keep exactly what they have today: ca_count = 1 and the
     * old ca_weight as the single CA's weight. CA 1 keeps using the original
     * ca_* columns; CA 2 and CA 3 get their own.
     */
    public function up(): void
    {
        Schema::table('assessment_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('ca_count')->default(1)->after('school_id');
            $table->unsignedTinyInteger('ca2_weight')->default(0)->after('ca_weight');
            $table->unsignedTinyInteger('ca3_weight')->default(0)->after('ca2_weight');
        });

        Schema::table('subject_scores', function (Blueprint $table) {
            $table->decimal('ca2_score', 5, 2)->nullable()->after('ca_max');
            $table->decimal('ca2_max', 5, 2)->nullable()->after('ca2_score');
            $table->decimal('ca3_score', 5, 2)->nullable()->after('ca2_max');
            $table->decimal('ca3_max', 5, 2)->nullable()->after('ca3_score');
        });
    }

    public function down(): void
    {
        Schema::table('subject_scores', function (Blueprint $table) {
            $table->dropColumn(['ca2_score', 'ca2_max', 'ca3_score', 'ca3_max']);
        });

        Schema::table('assessment_settings', function (Blueprint $table) {
            $table->dropColumn(['ca_count', 'ca2_weight', 'ca3_weight']);
        });
    }
};
