<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BookLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function search(Request $request, BookLookupService $lookupService): JsonResponse
    {
        $isbn = $request->query('isbn');
        $q = $request->query('q');

        $books = $lookupService->search($isbn, $q);

        return response()->json([
            'data' => $books,
        ]);
    }
}
