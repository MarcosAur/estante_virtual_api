<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\WritingEntryStoreRequest;
use App\Http\Requests\WritingEntryUpdateRequest;
use App\Http\Resources\WritingEntryResource;
use App\Models\WritingEntry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

class WritingEntryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = WritingEntry::with('tags');

        if ($request->filled('kind')) {
            $query->where('kind', $request->query('kind'));
        }

        if ($request->filled('tag')) {
            $tag = $request->query('tag');
            $query->whereHas('tags', function ($sub) use ($tag) {
                $sub->where('tags.id', $tag)
                    ->orWhere('tags.name', $tag);
            });
        }

        if ($request->filled('from')) {
            $query->where('entry_date', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->where('entry_date', '<=', $request->query('to'));
        }

        if ($request->filled('q')) {
            $q = $request->query('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('body', 'like', "%{$q}%")
                    ->orWhere('source_label', 'like', "%{$q}%");
            });
        }

        $sortDirection = strtolower($request->query('sort', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy('entry_date', $sortDirection)
            ->orderBy('created_at', $sortDirection);

        $perPage = (int) $request->query('per_page', 15);

        return WritingEntryResource::collection($query->paginate($perPage));
    }

    public function summary(Request $request): JsonResponse
    {
        $allEntries = WritingEntry::select('id', 'kind', 'entry_date')->get();

        $byKind = [
            'reflection' => 0,
            'reading' => 0,
            'note' => 0,
            'free' => 0,
        ];

        foreach ($allEntries as $entry) {
            if (isset($byKind[$entry->kind])) {
                $byKind[$entry->kind]++;
            }
        }

        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();

        $entriesThisMonth = $allEntries->filter(function ($entry) use ($startOfMonth, $endOfMonth) {
            $date = $entry->entry_date?->format('Y-m-d');
            return $date && $date >= $startOfMonth && $date <= $endOfMonth;
        })->count();

        // Distinct dates for streaks
        $distinctDates = $allEntries->map(fn($e) => $e->entry_date?->format('Y-m-d'))
            ->filter()
            ->unique()
            ->values();

        $streaks = $this->calculateStreaks($distinctDates);

        return response()->json([
            'data' => [
                'total' => $allEntries->count(),
                'by_kind' => $byKind,
                'entries_this_month' => $entriesThisMonth,
                'current_streak' => $streaks['current_streak'],
                'longest_streak' => $streaks['longest_streak'],
            ],
        ]);
    }

    public function store(WritingEntryStoreRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['tag_ids']);
        $entry = WritingEntry::create($data);

        if ($request->has('tag_ids') && is_array($request->tag_ids)) {
            $entry->tags()->sync($request->tag_ids);
        }

        return (new WritingEntryResource($entry->load('tags')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(WritingEntry $writing): WritingEntryResource
    {
        return new WritingEntryResource($writing->load('tags'));
    }

    public function update(WritingEntryUpdateRequest $request, WritingEntry $writing): WritingEntryResource
    {
        $data = $request->safe()->except(['tag_ids']);
        $writing->update($data);

        if ($request->has('tag_ids') && is_array($request->tag_ids)) {
            $writing->tags()->sync($request->tag_ids);
        }

        return new WritingEntryResource($writing->load('tags'));
    }

    public function destroy(WritingEntry $writing): JsonResponse
    {
        $writing->delete();

        return response()->json(['message' => 'Escrita removida com sucesso.']);
    }

    protected function calculateStreaks(Collection $dates): array
    {
        if ($dates->isEmpty()) {
            return ['current_streak' => 0, 'longest_streak' => 0];
        }

        $dateSet = $dates->flip();
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        // Current streak
        $currentStreak = 0;
        if ($dateSet->has($today)) {
            $checkDate = now();
            while ($dateSet->has($checkDate->toDateString())) {
                $currentStreak++;
                $checkDate = $checkDate->subDay();
            }
        } elseif ($dateSet->has($yesterday)) {
            $checkDate = now()->subDay();
            while ($dateSet->has($checkDate->toDateString())) {
                $currentStreak++;
                $checkDate = $checkDate->subDay();
            }
        }

        // Longest streak
        $sortedDates = $dates->map(fn($d) => Carbon::parse($d)->startOfDay())->sort()->values();
        $longestStreak = 0;
        $streak = 0;
        $prev = null;

        foreach ($sortedDates as $curr) {
            if ($prev === null) {
                $streak = 1;
            } elseif ($prev->copy()->addDay()->equalTo($curr)) {
                $streak++;
            } else {
                $streak = 1;
            }
            $longestStreak = max($longestStreak, $streak);
            $prev = $curr;
        }

        return [
            'current_streak' => $currentStreak,
            'longest_streak' => $longestStreak,
        ];
    }
}
