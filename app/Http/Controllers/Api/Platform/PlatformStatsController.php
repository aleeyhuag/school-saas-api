<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Services\PlatformStatsService;

class PlatformStatsController extends Controller
{
    public function show(PlatformStatsService $statsService)
    {
        return response()->json($statsService->build());
    }
}
