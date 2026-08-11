<?php

namespace App\Http\Controllers\Api\School;

use App\Http\Controllers\Controller;
use App\Services\SchoolHealthService;

class SchoolHealthController extends Controller
{
    public function show(SchoolHealthService $healthService)
    {
        return response()->json($healthService->build());
    }
}
