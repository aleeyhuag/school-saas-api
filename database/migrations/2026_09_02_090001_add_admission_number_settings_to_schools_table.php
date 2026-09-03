<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manual entry stays the default for every school, existing and
     * new — a school with its own existing numbering system should
     * never have that silently change out from under it. Schools that
     * want auto-generated numbers turn it on explicitly in Settings
     * (see SchoolSettingsController).
     */
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->boolean('auto_generate_admission_numbers')->default(false)->after('payment_reference_code');
            $table->unsignedInteger('admission_number_sequence')->default(0)->after('auto_generate_admission_numbers');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['auto_generate_admission_numbers', 'admission_number_sequence']);
        });
    }
};
