<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Records WHAT was actually paid. A single fee_structure can be
        // paid in installments, so this is many payments -> one fee
        // structure per student.
        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_structure_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_paid', 10, 2);
            $table->enum('method', ['cash', 'bank_transfer', 'paystack', 'flutterwave', 'other'])->default('cash');
            $table->string('reference')->nullable(); // gateway transaction ref, or teller number for bank transfer
            $table->enum('status', ['pending', 'completed', 'failed'])->default('completed');
            $table->date('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete(); // bursar who recorded a manual payment; null for gateway-initiated ones
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
    }
};
