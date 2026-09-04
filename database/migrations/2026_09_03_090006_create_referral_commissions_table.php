<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per payment that earned a commission — created inside
     * SubscriptionService::confirmPayment(), the same transaction that
     * marks the payment successful. rate_percentage_at_time snapshots
     * whatever the global rate was at that moment, so changing the
     * rate later (Super Admin billing page) never rewrites the
     * commission on a payment that already happened.
     */
    public function up(): void
    {
        Schema::create('referral_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->unsignedBigInteger('amount_kobo');
            $table->decimal('rate_percentage_at_time', 5, 2);
            $table->string('status')->default('pending'); // pending | paid
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['referral_partner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_commissions');
    }
};
