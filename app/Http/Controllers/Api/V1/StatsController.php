<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function index(Request $request, StatsService $statsService): JsonResponse
    {
        $year = $request->query('year') ? (int) $request->query('year') : null;

        return response()->json([
            'data' => $statsService->getStats($year),
        ]);
    }
}
