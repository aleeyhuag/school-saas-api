<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            // Lets the frontend show the RIGHT message when a school
            // is locked out — "your trial ended, subscribe" reads
            // very differently from "contact the platform admin."
            // Null means simply: currently active, no lock reason.
            $table->string('deactivation_reason')->nullable()->after('is_active');

            // Stable for the LIFE of the school — reused on every
            // payment, not regenerated each time. Makes it something
            // a Proprietor can memorise/reuse and something Super
            // Admin can recognise on a bank statement at a glance.
            $table->string('payment_reference_code')->nullable()->unique()->after('deactivation_reason');
        });

        // Backfill for schools that already exist.
        DB::table('schools')->whereNull('payment_reference_code')->orderBy('id')->each(function ($school) {
            DB::table('schools')->where('id', $school->id)->update([
                'payment_reference_code' => 'SCH-'.$school->id.'-'.strtoupper(\Illuminate\Support\Str::random(5)),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['deactivation_reason', 'payment_reference_code']);
        });
    }
};
