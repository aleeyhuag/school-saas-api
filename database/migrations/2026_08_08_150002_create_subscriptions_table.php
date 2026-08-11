<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            // trialing -> active -> (past_due, grace window) -> expired
            //                    \-> cancelled
            $table->enum('status', ['trialing', 'active', 'past_due', 'expired', 'cancelled'])
                ->default('trialing');
            $table->timestamp('trial_ends_at')->nullable();
            // When the CURRENT paid period runs out. Renewing just
            // pushes this forward by the plan's duration_months —
            // see SubscriptionService::activate().
            $table->timestamp('current_period_ends_at')->nullable();
            // Set the moment current_period_ends_at passes without a
            // confirmed renewal — the school stays active until THIS
            // passes too (3-day grace, confirmed choice, to cover
            // bank transfer confirmation lag). Null outside of a
            // past_due window.
            $table->timestamp('grace_ends_at')->nullable();
            $table->string('paystack_customer_code')->nullable();
            $table->string('paystack_subscription_code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
