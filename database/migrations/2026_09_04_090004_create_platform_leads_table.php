<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {public function up():void{Schema::create('platform_leads',function(Blueprint $t){$t->id();$t->string('email')->unique();$t->string('name')->nullable();$t->string('school_name')->nullable();$t->string('phone')->nullable();$t->string('source')->nullable();$t->boolean('unsubscribed')->default(false);$t->timestamps();});}public function down():void{Schema::dropIfExists('platform_leads');}};
