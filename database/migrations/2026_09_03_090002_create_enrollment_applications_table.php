<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A submitted application is NOT a Student — nothing here creates
     * a real enrolled student, a login, or touches a class roster
     * until a Proprietor/Principal explicitly approves it (see
     * EnrollmentApplicationController::approve()). This table exists
     * so an unpaid, unreviewed, possibly-spam submission never has a
     * chance to pollute real school data.
     */
    public function up(): void
    {
        Schema::create('enrollment_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->foreignId('school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->string('guardian_name');
            $table->string('guardian_email');
            $table->string('guardian_phone');
            // Private disk — same trust/storage level as subscription
            // payment proofs, not a publicly guessable path.
            $table->string('payment_proof_path')->nullable();
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            // Set once approved — lets the review queue link straight
            // to the resulting Student record.
            $table->foreignId('created_student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_applications');
    }
};
