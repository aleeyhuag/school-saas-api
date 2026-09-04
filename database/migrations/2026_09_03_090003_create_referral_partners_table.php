<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deliberately NOT tied to a users row — most referral partners
     * (teachers, uniform/textbook vendors, ICT technicians, an
     * existing proprietor referring a peer) have no reason to ever
     * log into Skulag at all. This is a standalone record Super Admin
     * manages directly, same shape as the Lead model from the
     * promotions system.
     */
    public function up(): void
    {
        Schema::create('referral_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('referral_code')->unique();
            $table->string('status')->default('active'); // active | inactive
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_partners');
    }
};
