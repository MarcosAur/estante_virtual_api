<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Movie;
use App\Models\RejectedSuggestion;
use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class AiSuggestionService
{
    /**
     * Generate suggestions for the user and replace previous ones atomically.
     */
    public function generate(User $user, string $type, string $prompt, bool $useHistory = true, array $streamingServices = []): Suggestion
    {
        $apiKey = config('services.openrouter.api_key');
        if (empty($apiKey)) {
            throw new RuntimeException('Chave de API do OpenRouter não configurada. Defina OPENROUTER_API_KEY no arquivo .env.');
        }

        $model = config('services.openrouter.model', 'google/gemini-3.5-flash');
        $baseUrl = rtrim(config('services.openrouter.base_url', 'https://openrouter.ai/api/v1'), '/');

        // 1. Collect known titles (shelf + current suggestions + last 30 rejected cache) to avoid repetitions
        $knownTitles = $this->getKnownTitles($user, $type);

        // 2. Build context from user's history (if requested)
        $context = $useHistory ? $this->buildUserContext($user, $type) : [];

        // 3. Call OpenRouter
        $items = $this->callOpenRouter($apiKey, $baseUrl, $model, $type, $prompt, $context, $knownTitles, $useHistory, $streamingServices);

        // 4. Atomically update/create suggestion, record unimported to rejected cache, and replace suggested_media
        return DB::transaction(function () use ($user, $type, $prompt, $model, $items, $useHistory, $streamingServices) {
            $suggestion = Suggestion::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('type', $type)
                ->first();

            if ($suggestion) {
                // Record unimported media as rejected before wiping them out
                $unimportedTitles = $suggestion->media()
                    ->whereNull('mediable_id')
                    ->pluck('title')
                    ->toArray();

                if (!empty($unimportedTitles)) {
                    $this->recordRejections($user, $type, $unimportedTitles);
                }

                $suggestion->update([
                    'prompt' => $prompt,
                    'model' => $model,
                    'streaming_services' => $type === 'movie' ? $streamingServices : null,
                    'use_history' => $useHistory,
                ]);
                $suggestion->media()->delete();
            } else {
                $suggestion = Suggestion::create([
                    'user_id' => $user->id,
                    'type' => $type,
                    'prompt' => $prompt,
                    'model' => $model,
                    'streaming_services' => $type === 'movie' ? $streamingServices : null,
                    'use_history' => $useHistory,
                ]);
            }

            // Fetch existing items for automatic matching
            $existingMap = $this->getExistingItemsMap($user, $type);

            $position = 1;
            foreach ($items as $item) {
                $normTitle = $this->normalizeString($item['title'] ?? '');
                $year = isset($item['year']) && is_numeric($item['year']) ? (int) $item['year'] : null;

                $match = $this->findMatch($existingMap, $normTitle, $year);

                $mediableType = null;
                $mediableId = null;

                if ($match) {
                    $mediableType = $type;
                    $mediableId = $match->id;
                }

                $suggestion->media()->create([
                    'position' => $position++,
                    'title' => trim($item['title'] ?? ''),
                    'creator' => trim($item['creator'] ?? '') ?: null,
                    'year' => $year,
                    'synopsis' => trim($item['synopsis'] ?? '') ?: null,
                    'reason' => trim($item['reason'] ?? '') ?: null,
                    'mediable_type' => $mediableType,
                    'mediable_id' => $mediableId,
                ]);
            }

            $suggestion->unsetRelation('media');
            $suggestion->load(['media.mediable']);

            return $suggestion;
        });
    }

    protected function buildUserContext(User $user, string $type): array
    {
        $context = [];

        if ($type === 'book') {
            // Highly rated books
            $highRated = Book::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->whereHas('review', fn($q) => $q->where('rating', '>=', 4.0))
                ->with('review')
                ->limit(20)
                ->get()
                ->map(fn($b) => [
                    'title' => $b->title,
                    'author' => $b->author,
                    'rating' => (float) $b->review->rating,
                    'notes' => Str::limit($b->review->body, 120),
                    'status' => 'liked',
                ]);

            // Low rated books (things to avoid)
            $lowRated = Book::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->whereHas('review', fn($q) => $q->where('rating', '<=', 2.5))
                ->with('review')
                ->limit(10)
                ->get()
                ->map(fn($b) => [
                    'title' => $b->title,
                    'author' => $b->author,
                    'rating' => (float) $b->review->rating,
                    'notes' => Str::limit($b->review->body, 120),
                    'status' => 'disliked',
                ]);

            // Wishlist
            $wishlist = Book::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('status', 'want_to_read')
                ->limit(20)
                ->get()
                ->map(fn($b) => [
                    'title' => $b->title,
                    'author' => $b->author,
                    'status' => 'want_to_read',
                ]);

            $context = array_merge($highRated->toArray(), $lowRated->toArray(), $wishlist->toArray());
        } else {
            // Movies
            $highRated = Movie::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->whereHas('review', fn($q) => $q->where('rating', '>=', 4.0))
                ->with('review')
                ->limit(20)
                ->get()
                ->map(fn($m) => [
                    'title' => $m->title,
                    'director' => $m->director,
                    'rating' => (float) $m->review->rating,
                    'notes' => Str::limit($m->review->body, 120),
                    'status' => 'liked',
                ]);

            $lowRated = Movie::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->whereHas('review', fn($q) => $q->where('rating', '<=', 2.5))
                ->with('review')
                ->limit(10)
                ->get()
                ->map(fn($m) => [
                    'title' => $m->title,
                    'director' => $m->director,
                    'rating' => (float) $m->review->rating,
                    'notes' => Str::limit($m->review->body, 120),
                    'status' => 'disliked',
                ]);

            $wishlist = Movie::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('status', 'want_to_watch')
                ->limit(20)
                ->get()
                ->map(fn($m) => [
                    'title' => $m->title,
                    'director' => $m->director,
                    'status' => 'want_to_watch',
                ]);

            $context = array_merge($highRated->toArray(), $lowRated->toArray(), $wishlist->toArray());
        }

        return $context;
    }

    protected function getKnownTitles(User $user, string $type): array
    {
        $shelfTitles = [];
        if ($type === 'book') {
            $shelfTitles = Book::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->pluck('title')
                ->toArray();
        } else {
            $shelfTitles = Movie::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->pluck('title')
                ->toArray();
        }

        $existingSuggestion = Suggestion::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->first();

        $suggestedTitles = [];
        if ($existingSuggestion) {
            $suggestedTitles = $existingSuggestion->media()->pluck('title')->toArray();
        }

        // Fetch last 30 rejected titles from cache
        $rejectedTitles = RejectedSuggestion::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(30)
            ->pluck('title')
            ->toArray();

        return array_values(array_unique(array_filter(array_merge($shelfTitles, $suggestedTitles, $rejectedTitles))));
    }

    /**
     * Store rejected titles in cache, keeping strictly the latest 30 titles for this user and type.
     */
    public function recordRejections(User $user, string $type, array $titles): void
    {
        foreach ($titles as $rawTitle) {
            $rawTitle = trim($rawTitle);
            if (empty($rawTitle)) {
                continue;
            }

            $normalized = $this->normalizeString($rawTitle);

            RejectedSuggestion::withoutGlobalScopes()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => $type,
                    'normalized_title' => $normalized,
                ],
                [
                    'title' => $rawTitle,
                    'updated_at' => now(),
                ]
            );
        }

        // Keep strictly the latest 30 rejected titles for this user and type (FIFO cache)
        $idsToKeep = RejectedSuggestion::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(30)
            ->pluck('id');

        if ($idsToKeep->isNotEmpty()) {
            RejectedSuggestion::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('type', $type)
                ->whereNotIn('id', $idsToKeep)
                ->delete();
        }
    }

    protected function callOpenRouter(
        string $apiKey,
        string $baseUrl,
        string $model,
        string $type,
        string $prompt,
        array $context,
        array $knownTitles,
        bool $useHistory,
        array $streamingServices = []
    ): array {
        $typeLabel = $type === 'book' ? 'livros' : 'filmes';
        $creatorLabel = $type === 'book' ? 'autor' : 'diretor';

        $streamingInstruction = '';
        if ($type === 'movie' && !empty($streamingServices)) {
            $streamingList = implode(', ', $streamingServices);
            $streamingInstruction = "\nIMPORTANTE SOBRE STREAMING: O usuário selecionou os seguintes serviços de streaming: {$streamingList}. Você DEVE sugerir filmes disponíveis no catálogo brasileiro desses serviços. No campo 'reason', mencione onde assistir.";
        }

        $portugueseTitleInstruction = "\nIMPORTANTE SOBRE O IDIOMA DOS TÍTULOS: No campo 'title', retorne OBRIGATORIAMENTE o nome da obra em português (título oficial de lançamento ou distribuição no Brasil). No caso de obras e filmes estrangeiros, utilize SEMPRE o título em português pelo qual foram lançados no Brasil (ex: 'O Poderoso Chefão', 'A Origem', 'Brilho Eterno de uma Mente sem Lembranças', 'Interestelar', 'Um Sonho de Liberdade', etc.), NUNCA retorne apenas o título original em outro idioma.";

        if ($useHistory) {
            $systemPrompt = "Você é um consultor especializado em recomendações de {$typeLabel}.
O usuário busca novas recomendações personalizadas com base em seu perfil e histórico de avaliações.
Analise com atenção o histórico de avaliações e lista de desejos da estante do usuário para inferir gostos, estilos, temas e o que evitar.
IMPORTANTE: Não sugira nenhuma obra que já conste na lista de obras conhecidas, sugeridas anteriormente ou recentemente rejeitadas.{$portugueseTitleInstruction}
Gere exatamente 10 recomendações originais e relevantes para o pedido.{$streamingInstruction}
Retorne um JSON contendo uma lista sob a chave 'suggestions'. Cada item deve ter:
- title: título da obra em português (título oficial no Brasil)
- creator: {$creatorLabel} da obra
- year: ano de lançamento ou publicação (inteiro)
- synopsis: sinopse envolvente (1 a 3 frases)
- reason: explicação personalizada conectando ao gosto do usuário e ao pedido feito.";
        } else {
            $systemPrompt = "Você é um consultor especializado em recomendações de {$typeLabel}.
O usuário busca descobrir obras novas e diversificadas, optando deliberadamente por NÃO usar seu histórico de avaliações para evitar viés.
Concentre-se em surpreender positivamente com obras inovadoras, aclamadas ou interessantes que atendam estritamente ao pedido do usuário.
IMPORTANTE: Não sugira nenhuma obra que já conste na lista de obras conhecidas, sugeridas anteriormente ou recentemente rejeitadas.{$portugueseTitleInstruction}
Gere exatamente 10 recomendações originais e relevantes para o pedido.{$streamingInstruction}
Retorne um JSON contendo uma lista sob a chave 'suggestions'. Cada item deve ter:
- title: título da obra em português (título oficial no Brasil)
- creator: {$creatorLabel} da obra
- year: ano de lançamento ou publicação (inteiro)
- synopsis: sinopse envolvente (1 a 3 frases)
- reason: explicação do porquê essa recomendação é interessante para o pedido.";
        }

        $userPromptParts = [];

        if ($useHistory && !empty($context)) {
            $userPromptParts[] = "Histórico de avaliações da estante:\n" . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        if ($type === 'movie' && !empty($streamingServices)) {
            $userPromptParts[] = "Serviços de streaming disponíveis selecionados: " . implode(', ', $streamingServices) . " (recomendar apenas obras disponíveis nestas plataformas).";
        }

        if (!empty($knownTitles)) {
            $userPromptParts[] = "Obras que já constam na estante ou que foram recentemente rejeitadas/sugeridas (NÃO sugerir nenhuma destas sob hipótese alguma):\n- " . implode("\n- ", array_slice($knownTitles, 0, 100));
        }

        $userPromptParts[] = "Meu pedido:\n" . $prompt;
        $userPromptParts[] = "Lembre-se: Retorne todos os títulos ('title') em português (título oficial de lançamento no Brasil).";

        $userPrompt = implode("\n\n", $userPromptParts);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'HTTP-Referer' => 'http://localhost',
                'X-Title' => 'Estante Virtual',
            ])
            ->timeout(60)
            ->post($baseUrl . '/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'media_suggestions',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'suggestions' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'title' => ['type' => 'string'],
                                            'creator' => ['type' => 'string'],
                                            'year' => ['type' => ['integer', 'null']],
                                            'synopsis' => ['type' => 'string'],
                                            'reason' => ['type' => 'string'],
                                        ],
                                        'required' => ['title', 'creator', 'year', 'synopsis', 'reason'],
                                        'additionalProperties' => false,
                                    ],
                                ],
                            ],
                            'required' => ['suggestions'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new RuntimeException('Falha de conexão com o serviço de IA. Verifique sua conexão e tente novamente.');
        }

        if ($response->status() === 429) {
            throw new RuntimeException('Limite de requisições do OpenRouter excedido. Tente novamente em alguns instantes.');
        }

        if ($response->failed()) {
            $errorMsg = $response->json('error.message') ?? $response->body();
            throw new RuntimeException('Erro na API do OpenRouter: ' . Str::limit($errorMsg, 200));
        }

        $content = $response->json('choices.0.message.content');
        if (empty($content)) {
            throw new RuntimeException('A IA retornou uma resposta vazia. Tente novamente.');
        }

        // Clean markdown backticks if present
        $cleanContent = trim($content);
        if (str_starts_with($cleanContent, '```')) {
            $cleanContent = preg_replace('/^```(?:json)?\s*/i', '', $cleanContent);
            $cleanContent = preg_replace('/\s*```$/', '', $cleanContent);
        }

        $data = json_decode($cleanContent, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new RuntimeException('A IA retornou uma resposta em formato inválido. Tente novamente.');
        }

        $items = $data['suggestions'] ?? $data;
        if (!is_array($items)) {
            throw new RuntimeException('A resposta da IA não contém uma lista de sugestões válida.');
        }

        return $items;
    }

    protected function getExistingItemsMap(User $user, string $type): array
    {
        if ($type === 'book') {
            return Book::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->get()
                ->all();
        }

        return Movie::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->get()
            ->all();
    }

    protected function findMatch(array $existingItems, string $normTitle, ?int $year): ?object
    {
        if (empty($normTitle)) {
            return null;
        }

        foreach ($existingItems as $item) {
            $itemNorm = $this->normalizeString($item->title);
            if ($itemNorm === $normTitle) {
                // If year is given on both, verify match or close match
                if ($year !== null && !empty($item->year)) {
                    if (abs($item->year - $year) <= 1) {
                        return $item;
                    }
                } else {
                    return $item;
                }
            }
        }

        return null;
    }

    public function normalizeString(string $str): string
    {
        $str = mb_strtolower(trim($str), 'UTF-8');
        // Transliterate accents
        $str = preg_replace('~[^\pL\d]+~u', '', $str);
        return $str;
    }
}
