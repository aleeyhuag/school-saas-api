<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbt_exams', function (Blueprint $table) {
            $table->softDeletes();
            $table->index(['school_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('cbt_exams', function (Blueprint $table) {
            $table->dropIndex(['cbt_exams_school_id_deleted_at_index']);
            $table->dropSoftDeletes();
        });
    }
};
