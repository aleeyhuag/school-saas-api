<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Correction: "₦13,000 per 3 terms" means ₦13,000 EACH term
     * (paid 3 times across an academic year, totalling ₦39,000) — not
     * ₦13,000 once for the whole year. duration_months=12 was wrong;
     * one term is ~4 months, so the plan should renew every 4 months.
     * The price itself (₦13,000 = 1,300,000 kobo) was already correct.
     */
    public function up(): void
    {
        DB::table('plans')
            ->where('billing_interval', 'termly')
            ->update(['duration_months' => 4]);
    }

    public function down(): void
    {
        DB::table('plans')
            ->where('billing_interval', 'termly')
            ->update(['duration_months' => 12]);
    }
};
