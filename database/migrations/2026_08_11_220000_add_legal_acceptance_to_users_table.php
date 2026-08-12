<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('terms_version')->nullable()->after('status');
            $table->string('privacy_version')->nullable()->after('terms_version');
            $table->timestamp('legal_accepted_at')->nullable()->after('privacy_version');
            $table->ipAddress('legal_accepted_ip')->nullable()->after('legal_accepted_at');
            $table->text('legal_accepted_user_agent')->nullable()->after('legal_accepted_ip');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'terms_version', 'privacy_version', 'legal_accepted_at',
                'legal_accepted_ip', 'legal_accepted_user_agent',
            ]);
        });
    }
};
