<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BookLookupService;
use App\Services\MovieLookupService;
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

    public function searchMovies(Request $request, MovieLookupService $movieLookupService): JsonResponse
    {
        $q = $request->query('q');

        $movies = $movieLookupService->search($q);

        return response()->json([
            'data' => $movies,
        ]);
    }
}
