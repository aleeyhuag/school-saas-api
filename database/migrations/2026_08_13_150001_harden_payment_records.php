<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Snapshot the plan duration at the moment payment is submitted.
            // A later plan edit must not silently change the period purchased.
            $table->unsignedInteger('duration_months')->nullable()->after('amount_kobo');
            $table->index(['school_id', 'status']);
        });

        Schema::create('payment_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20); // confirmed | rejected
            $table->string('previous_status', 30);
            $table->string('new_status', 30);
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payment_id', 'created_at']);
            $table->index(['reviewer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reviews');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'status']);
            $table->dropColumn('duration_months');
        });
    }
};
