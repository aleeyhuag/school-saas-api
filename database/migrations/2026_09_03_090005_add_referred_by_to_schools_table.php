<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->foreignId('referred_by_partner_id')->nullable()->after('school_group_id')
                ->constrained('referral_partners')->nullOnDelete();
            // Set once, on the FIRST commission-earning payment for
            // this school (see SubscriptionService::confirmPayment).
            // Every later payment's commission tier (15% / 5% / none)
            // is measured against this fixed point, not against
            // registration date or trial start — a school sitting in
            // trial for weeks before their first payment shouldn't
            // burn into their referral partner's 12-month window.
            $table->timestamp('referral_commission_start_at')->nullable()->after('referred_by_partner_id');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_partner_id');
            $table->dropColumn('referral_commission_start_at');
        });
    }
};
