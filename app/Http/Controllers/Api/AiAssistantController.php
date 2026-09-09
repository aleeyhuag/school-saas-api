<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SkulagAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AiAssistantController extends Controller
{
    public function __invoke(Request $request, SkulagAiService $ai): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'history' => ['sometimes', 'array', 'max:12'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:2500'],
        ]);

        $user = $request->user();

        if ($user->school_id && ! $user->school?->is_active) {
            return response()->json([
                'message' => 'Your school account is currently disabled. Please use Billing or contact your school administrator.',
            ], 403);
        }

        try {
            $answer = $ai->answer(
                $user,
                $validated['history'] ?? [],
                $validated['message']
            );

            return response()->json([
                'answer' => $answer,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage(),
            ], 503);
        }
    }
}
