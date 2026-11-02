<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BatchImportSuggestionRequest;
use App\Http\Requests\GenerateSuggestionRequest;
use App\Http\Requests\ImportSuggestedMediaRequest;
use App\Http\Resources\SuggestedMediaResource;
use App\Http\Resources\SuggestionResource;
use App\Models\Book;
use App\Models\Movie;
use App\Models\SuggestedMedia;
use App\Models\Suggestion;
use App\Services\AiSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SuggestionController extends Controller
{
    public function showLatest(Request $request): JsonResponse
    {
        $type = $request->query('type', 'book');
        if (!in_array($type, ['book', 'movie'], true)) {
            return response()->json(['message' => 'Tipo inválido. Deve ser book ou movie.'], 422);
        }

        $suggestion = Suggestion::where('user_id', Auth::id())
            ->where('type', $type)
            ->with(['media.mediable'])
            ->first();

        return response()->json([
            'data' => $suggestion ? new SuggestionResource($suggestion) : null,
        ]);
    }

    public function generate(GenerateSuggestionRequest $request, AiSuggestionService $service): JsonResponse
    {
        $user = Auth::user();

        try {
            $suggestion = $service->generate(
                $user,
                $request->input('type'),
                $request->input('prompt'),
                $request->boolean('use_history', true)
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => new SuggestionResource($suggestion),
        ], 201);
    }

    public function importItem(ImportSuggestedMediaRequest $request, string $id): JsonResponse
    {
        $userId = Auth::id();
        $item = SuggestedMedia::where('id', $id)
            ->whereHas('suggestion', fn($q) => $q->where('user_id', $userId))
            ->with('suggestion')
            ->first();

        if (!$item) {
            return response()->json(['message' => 'Item sugerido não encontrado.'], 404);
        }

        $type = $item->suggestion->type;
        $status = $request->input('status');

        $this->validateStatusForType($type, $status);

        return DB::transaction(function () use ($item, $type, $status) {
            $locked = SuggestedMedia::where('id', $item->id)->lockForUpdate()->first();

            if ($locked->mediable && !$locked->mediable->trashed()) {
                return response()->json([
                    'message' => 'Este item já foi adicionado à estante.',
                ], 409);
            }

            $mediaResource = $this->createResourceFromSuggestedMedia($locked, $type, $status);

            $locked->update([
                'mediable_type' => $type,
                'mediable_id' => $mediaResource->id,
            ]);

            return response()->json([
                'data' => new SuggestedMediaResource($locked->fresh(['mediable'])),
            ]);
        });
    }

    public function batchImport(BatchImportSuggestionRequest $request, string $id): JsonResponse
    {
        $userId = Auth::id();
        $suggestion = Suggestion::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$suggestion) {
            return response()->json(['message' => 'Sugestão não encontrada.'], 404);
        }

        $type = $suggestion->type;
        $status = $request->input('status');

        $this->validateStatusForType($type, $status);

        $itemIds = array_unique($request->input('item_ids'));
        $items = SuggestedMedia::where('suggestion_id', $suggestion->id)
            ->whereIn('id', $itemIds)
            ->get();

        if ($items->count() !== count($itemIds)) {
            return response()->json([
                'message' => 'Um ou mais itens informados são inválidos ou não pertencem a esta sugestão.',
            ], 422);
        }

        $created = [];
        $skipped = [];

        foreach ($items as $item) {
            $result = DB::transaction(function () use ($item, $type, $status) {
                $locked = SuggestedMedia::where('id', $item->id)->lockForUpdate()->first();

                if ($locked->mediable && !$locked->mediable->trashed()) {
                    return ['status' => 'skipped', 'item' => $locked];
                }

                $resource = $this->createResourceFromSuggestedMedia($locked, $type, $status);

                $locked->update([
                    'mediable_type' => $type,
                    'mediable_id' => $resource->id,
                ]);

                return ['status' => 'created', 'item' => $locked];
            });

            if ($result['status'] === 'created') {
                $created[] = new SuggestedMediaResource($result['item']->fresh(['mediable']));
            } else {
                $skipped[] = new SuggestedMediaResource($result['item']->fresh(['mediable']));
            }
        }

        return response()->json([
            'data' => [
                'created_count' => count($created),
                'skipped_count' => count($skipped),
                'created' => $created,
                'skipped' => $skipped,
            ],
        ]);
    }

    protected function validateStatusForType(string $type, string $status): void
    {
        $validStatuses = $type === 'book'
            ? ['want_to_read', 'reading', 'read']
            : ['want_to_watch', 'watching', 'watched'];

        if (!in_array($status, $validStatuses, true)) {
            abort(422, "Status '{$status}' é inválido para o tipo {$type}.");
        }
    }

    protected function createResourceFromSuggestedMedia(SuggestedMedia $item, string $type, string $status): object
    {
        if ($type === 'book') {
            return Book::create([
                'title' => $item->title,
                'author' => $item->creator ?? 'Desconhecido',
                'year' => $item->year,
                'synopsis' => $item->synopsis,
                'status' => $status,
                'current_page' => 0,
                'pages' => 0,
            ]);
        }

        return Movie::create([
            'title' => $item->title,
            'director' => $item->creator,
            'year' => $item->year,
            'synopsis' => $item->synopsis,
            'status' => $status,
            'runtime_minutes' => 0,
        ]);
    }
}
