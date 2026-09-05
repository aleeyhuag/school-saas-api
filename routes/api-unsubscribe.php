<?php
use Illuminate\Support\Facades\Route; use App\Http\Controllers\Api\Platform\PlatformLeadController;
Route::get('unsubscribe/{email}', [PlatformLeadController::class,'unsubscribe'])->middleware('signed')->name('marketing.unsubscribe');
