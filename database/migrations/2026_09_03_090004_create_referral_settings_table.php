<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Single-row settings table rather than a config file — the whole
     * point is that both rates are editable from the Super Admin
     * billing page without a redeploy, same spirit as editing a
     * Plan's price there.
     *
     * Two tiers, not one: 15% on every payment (monthly or termly,
     * doesn't matter which) for a referred school's first 12 months
     * as a paying customer, then 5% for the next 12 months, then
     * nothing. See SubscriptionService::confirmPayment() for exactly
     * how "first 12 months" is measured — from the school's first
     * successful payment, not from registration or trial start.
     */
    public function up(): void
    {
        Schema::create('referral_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('year_one_percentage', 5, 2)->default(15.00);
            $table->decimal('year_two_percentage', 5, 2)->default(5.00);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        DB::table('referral_settings')->insert([
            'year_one_percentage' => 15.00,
            'year_two_percentage' => 5.00,
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_settings');
    }
};
