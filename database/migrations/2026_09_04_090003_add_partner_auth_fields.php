<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('referral_partners', function(Blueprint $t){$t->string('password')->nullable()->after('email');$t->timestamp('email_verified_at')->nullable()->after('password');}); } public function down(): void {Schema::table('referral_partners',fn(Blueprint $t)=>$t->dropColumn(['password','email_verified_at']));} };
