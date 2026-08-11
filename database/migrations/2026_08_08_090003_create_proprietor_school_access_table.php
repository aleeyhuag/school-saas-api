<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every OTHER role stays tied to exactly one school via
     * users.school_id, unchanged. Only a Proprietor can have more
     * than one row here — this is what lets a single login "switch"
     * between branches (BranchController::switchBranch() just
     * updates users.school_id to whichever branch was picked, after
     * confirming a row exists here for it — everything else in the
     * app keeps reading Auth::user()->school_id exactly as before).
     */
    public function up(): void
    {
        Schema::create('proprietor_school_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proprietor_school_access');
    }
};
