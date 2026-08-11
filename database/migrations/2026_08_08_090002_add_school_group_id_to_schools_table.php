<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            // Nullable — most schools on the platform are standalone
            // and never need this at all. Only set once a Proprietor
            // adds a second branch (see BranchController::store()).
            $table->foreignId('school_group_id')->nullable()->after('id')
                ->constrained('school_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_group_id');
        });
    }
};
