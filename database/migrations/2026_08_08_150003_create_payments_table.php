<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->unsignedBigInteger('amount_kobo');
            $table->enum('method', ['bank_transfer', 'paystack'])->default('bank_transfer');
            $table->enum('status', ['pending_review', 'success', 'rejected'])->default('pending_review');
            // Copied from the school's own stable reference code at
            // submission time (see schools.payment_reference_code) —
            // stored here too so a payment's history is self-
            // contained even if the school's code ever changed.
            $table->string('reference_code');
            // Required for bank_transfer (confirmed: proof upload is
            // mandatory, not just a declared reference). Nullable
            // only because a future Paystack-verified payment won't
            // have one — the auto-confirmed webhook path doesn't need
            // a human-reviewable image.
            $table->string('proof_path')->nullable();
            $table->string('paystack_reference')->nullable()->unique();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('reference_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
