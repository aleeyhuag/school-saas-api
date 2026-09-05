<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('account_action_tokens', function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('purpose');$t->string('token_hash',64)->unique();$t->timestamp('expires_at');$t->timestamps();$t->index(['user_id','purpose']);}); } public function down(): void {Schema::dropIfExists('account_action_tokens');} };
