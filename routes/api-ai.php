<?php

use App\Http\Controllers\Api\AiAssistantController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:10,1'])->post('ai/ask', AiAssistantController::class);
