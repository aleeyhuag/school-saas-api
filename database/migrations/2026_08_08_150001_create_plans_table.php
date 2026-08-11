<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Stored in kobo (smallest currency unit), matching how
            // Paystack itself represents amounts — avoids float
            // rounding issues entirely. 350000 kobo = ₦3,500.
            $table->unsignedBigInteger('amount_kobo');
            // How far to push current_period_ends_at forward when a
            // payment for this plan is confirmed — this is what
            // actually drives renewal timing, not billing_interval,
            // which is just a display label.
            $table->unsignedTinyInteger('duration_months');
            $table->string('billing_interval'); // display label only, e.g. "monthly", "termly"
            $table->string('paystack_plan_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('plans')->insert([
            [
                'name' => 'Monthly',
                'amount_kobo' => 350000, // ₦3,500
                'duration_months' => 1,
                'billing_interval' => 'monthly',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                // "3 terms" read as one Nigerian academic session
                // (~12 months). Editable via Super Admin > Billing if
                // that assumption needs adjusting.
                'name' => 'Termly (3 Terms)',
                'amount_kobo' => 1300000, // ₦13,000
                'duration_months' => 4,
                'billing_interval' => 'termly',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
