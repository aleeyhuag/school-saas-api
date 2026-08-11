<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A group ties multiple independent School rows together under
     * one owner — e.g. "Bright Future Group" with a Lagos branch and
     * an Abuja branch. Each branch stays a fully separate School row
     * with its own classes/staff/students/fees (confirmed: no shared
     * data between branches) — this table is purely for "which
     * schools belong to the same group," nothing operational lives
     * here.
     */
    public function up(): void
    {
        Schema::create('school_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_groups');
    }
};
