<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Movie;
use App\Models\RejectedSuggestion;
use App\Models\SuggestedMedia;
use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuggestionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
        Config::set('services.openrouter.api_key', 'test-key');
    }

    private function mockOpenRouterSuccess(array $suggestions): void
    {
        Http::fake([
            '*openrouter.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode(['suggestions' => $suggestions]),
                        ],
                    ],
                ],
            ], 200),
        ]);
    }

    public function test_can_generate_suggestions_and_replaces_previous_pivot_records(): void
    {
        Http::fake([
            '*openrouter.ai/*' => Http::sequence()
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode(['suggestions' => [
                                    [
                                        'title' => 'Duna',
                                        'creator' => 'Frank Herbert',
                                        'year' => 1965,
                                        'synopsis' => 'Um clássico de ficção científica.',
                                        'reason' => 'Você gosta de mundos complexos.',
                                    ],
                                    [
                                        'title' => 'Fundação',
                                        'creator' => 'Isaac Asimov',
                                        'year' => 1951,
                                        'synopsis' => 'A queda do império galáctico.',
                                        'reason' => 'Grande escala histórica.',
                                    ],
                                ]]),
                            ],
                        ],
                    ],
                ])
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode(['suggestions' => [
                                    [
                                        'title' => 'Neuromancer',
                                        'creator' => 'William Gibson',
                                        'year' => 1984,
                                        'synopsis' => 'O nascimento do cyberpunk.',
                                        'reason' => 'Pioneiro do gênero.',
                                    ],
                                ]]),
                            ],
                        ],
                    ],
                ]),
        ]);

        $response = $this->postJson('/api/v1/suggestions', [
            'type' => 'book',
            'prompt' => 'Quero ficção científica clássica',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'book')
            ->assertJsonPath('data.prompt', 'Quero ficção científica clássica')
            ->assertJsonCount(2, 'data.media')
            ->assertJsonPath('data.media.0.status', 'suggested');

        $this->assertDatabaseCount('suggestions', 1);
        $this->assertDatabaseCount('suggested_media', 2);

        // Second generation replaces pivot table
        $response2 = $this->postJson('/api/v1/suggestions', [
            'type' => 'book',
            'prompt' => 'Quero algo cyberpunk',
        ]);

        $response2->assertStatus(201)
            ->assertJsonCount(1, 'data.media')
            ->assertJsonPath('data.media.0.title', 'Neuromancer');

        // Still only 1 suggestion in DB, and only 1 suggested_media in DB
        $this->assertDatabaseCount('suggestions', 1);
        $this->assertDatabaseCount('suggested_media', 1);
    }

    public function test_ai_failure_does_not_destroy_previous_suggestions(): void
    {
        Http::fake([
            '*openrouter.ai/*' => Http::sequence()
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode(['suggestions' => [
                                    [
                                        'title' => 'Duna',
                                        'creator' => 'Frank Herbert',
                                        'year' => 1965,
                                        'synopsis' => 'Sinopse',
                                        'reason' => 'Motivo',
                                    ],
                                ]]),
                            ],
                        ],
                    ],
                ])
                ->push(['error' => ['message' => 'Rate limit exceeded']], 429),
        ]);

        $this->postJson('/api/v1/suggestions', [
            'type' => 'book',
            'prompt' => 'Primeiro pedido',
        ])->assertStatus(201);

        $this->assertDatabaseCount('suggested_media', 1);

        // Second request fails due to 429
        $response = $this->postJson('/api/v1/suggestions', [
            'type' => 'book',
            'prompt' => 'Segundo pedido que falhará',
        ]);

        $response->assertStatus(422);

        // Previous suggestion is still preserved!
        $this->assertDatabaseCount('suggested_media', 1);
        $this->assertDatabaseHas('suggestions', ['prompt' => 'Primeiro pedido']);
    }

    public function test_automatic_matching_with_existing_shelf_items(): void
    {
        // User already has Duna in their shelf
        $book = Book::create([
            'title' => 'Duna',
            'author' => 'Frank Herbert',
            'year' => 1965,
            'status' => 'reading',
        ]);

        $this->mockOpenRouterSuccess([
            [
                'title' => 'duna',
                'creator' => 'Frank Herbert',
                'year' => 1965,
                'synopsis' => 'Sinopse',
                'reason' => 'Motivo',
            ],
            [
                'title' => 'O Fim da Infância',
                'creator' => 'Arthur C. Clarke',
                'year' => 1953,
                'synopsis' => 'Sinopse',
                'reason' => 'Motivo',
            ],
        ]);

        $response = $this->postJson('/api/v1/suggestions', [
            'type' => 'book',
            'prompt' => 'Sugestões',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.media.0.status', 'reading')
            ->assertJsonPath('data.media.0.mediable_id', $book->id)
            ->assertJsonPath('data.media.1.status', 'suggested')
            ->assertJsonPath('data.media.1.mediable_id', null);
    }

    public function test_can_import_single_item(): void
    {
        $this->mockOpenRouterSuccess([
            [
                'title' => 'O Poderoso Chefão',
                'creator' => 'Francis Ford Coppola',
                'year' => 1972,
                'synopsis' => 'Uma família da máfia.',
                'reason' => 'Obra-prima do cinema.',
            ],
        ]);

        $res = $this->postJson('/api/v1/suggestions', [
            'type' => 'movie',
            'prompt' => 'Cinema clássico',
        ]);

        $itemId = $res->json('data.media.0.id');

        $importRes = $this->postJson("/api/v1/suggested-media/{$itemId}/import", [
            'status' => 'want_to_watch',
        ]);

        $importRes->assertStatus(200)
            ->assertJsonPath('data.status', 'want_to_watch')
            ->assertJsonPath('data.mediable_type', 'movie');

        $movieId = $importRes->json('data.mediable_id');
        $this->assertNotNull($movieId);
        $this->assertDatabaseHas('movies', [
            'id' => $movieId,
            'title' => 'O Poderoso Chefão',
            'status' => 'want_to_watch',
        ]);

        // Attempting to import same item again returns 409
        $conflictRes = $this->postJson("/api/v1/suggested-media/{$itemId}/import", [
            'status' => 'watched',
        ]);

        $conflictRes->assertStatus(409);
        $this->assertDatabaseCount('movies', 1);
    }

    public function test_validates_status_according_to_media_type(): void
    {
        $this->mockOpenRouterSuccess([
            [
                'title' => 'Livro Teste',
                'creator' => 'Autor Teste',
                'year' => 2020,
                'synopsis' => 'Sinopse',
                'reason' => 'Motivo',
            ],
        ]);

        $res = $this->postJson('/api/v1/suggestions', [
            'type' => 'book',
            'prompt' => 'Pedido',
        ]);

        $itemId = $res->json('data.media.0.id');

        // Trying to import a book with a movie status like 'watched' fails
        $badRes = $this->postJson("/api/v1/suggested-media/{$itemId}/import", [
            'status' => 'watched',
        ]);

        $badRes->assertStatus(422);
    }

    public function test_batch_import_imports_available_and_skips_already_added(): void
    {
        $this->mockOpenRouterSuccess([
            [
                'title' => 'Filme 1',
                'creator' => 'Diretor 1',
                'year' => 2001,
                'synopsis' => 'Sinopse 1',
                'reason' => 'Motivo 1',
            ],
            [
                'title' => 'Filme 2',
                'creator' => 'Diretor 2',
                'year' => 2002,
                'synopsis' => 'Sinopse 2',
                'reason' => 'Motivo 2',
            ],
        ]);

        $genRes = $this->postJson('/api/v1/suggestions', [
            'type' => 'movie',
            'prompt' => 'Filmes anos 2000',
        ]);

        $suggestionId = $genRes->json('data.id');
        $item1Id = $genRes->json('data.media.0.id');
        $item2Id = $genRes->json('data.media.1.id');

        // Import item 1 individually first
        $this->postJson("/api/v1/suggested-media/{$item1Id}/import", [
            'status' => 'watched',
        ])->assertStatus(200);

        // Now run batch import with both items
        $batchRes = $this->postJson("/api/v1/suggestions/{$suggestionId}/import", [
            'status' => 'want_to_watch',
            'item_ids' => [$item1Id, $item2Id],
        ]);

        $batchRes->assertStatus(200)
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.skipped_count', 1);

        $this->assertDatabaseCount('movies', 2);
    }

    public function test_batch_import_rejects_items_from_different_suggestion_or_user(): void
    {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);
        $otherSuggestion = Suggestion::create([
            'type' => 'book',
            'prompt' => 'Outro usuário',
            'model' => 'test-model',
        ]);
        $otherItem = SuggestedMedia::create([
            'suggestion_id' => $otherSuggestion->id,
            'title' => 'Livro Alheio',
            'position' => 1,
        ]);

        Sanctum::actingAs($this->user);

        $this->mockOpenRouterSuccess([
            [
                'title' => 'Meu Livro',
                'creator' => 'Meu Autor',
                'year' => 2021,
                'synopsis' => 'Sinopse',
                'reason' => 'Motivo',
            ],
        ]);

        $genRes = $this->postJson('/api/v1/suggestions', [
            'type' => 'book',
            'prompt' => 'Meu pedido',
        ]);

        $mySuggestionId = $genRes->json('data.id');

        // Trying to batch import including another user's item
        $res = $this->postJson("/api/v1/suggestions/{$mySuggestionId}/import", [
            'status' => 'want_to_read',
            'item_ids' => [$otherItem->id],
        ]);

        $res->assertStatus(422);
    }

    public function test_user_cannot_access_or_import_another_users_suggestions(): void
    {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);
        $otherSuggestion = Suggestion::create([
            'type' => 'movie',
            'prompt' => 'Outro usuário',
            'model' => 'test-model',
        ]);
        $otherItem = SuggestedMedia::create([
            'suggestion_id' => $otherSuggestion->id,
            'title' => 'Filme de B',
            'position' => 1,
        ]);

        Sanctum::actingAs($this->user);

        // User A cannot import User B's item
        $res = $this->postJson("/api/v1/suggested-media/{$otherItem->id}/import", [
            'status' => 'want_to_watch',
        ]);

        $res->assertStatus(404);
    }

    public function test_soft_deleted_resource_causes_suggested_status(): void
    {
        $book = Book::create([
            'title' => 'Livro Deletado',
            'author' => 'Autor',
            'status' => 'read',
        ]);

        $this->mockOpenRouterSuccess([
            [
                'title' => 'Livro Deletado',
                'creator' => 'Autor',
                'year' => null,
                'synopsis' => 'Sinopse',
                'reason' => 'Motivo',
            ],
        ]);

        $this->postJson('/api/v1/suggestions', [
            'type' => 'book',
            'prompt' => 'Pedido',
        ]);

        // Soft delete the book
        $book->delete();

        $getRes = $this->getJson('/api/v1/suggestions?type=book');
        $getRes->assertStatus(200)
            ->assertJsonPath('data.media.0.status', 'suggested')
            ->assertJsonPath('data.media.0.mediable_id', null);
    }

    public function test_can_generate_with_use_history_disabled(): void
    {
        $this->mockOpenRouterSuccess([
            [
                'title' => 'Livro Inovador',
                'creator' => 'Autor Novo',
                'year' => 2024,
                'synopsis' => 'Algo totalmente novo.',
                'reason' => 'Sem viés de histórico.',
            ],
        ]);

        $res = $this->postJson('/api/v1/suggestions', [
            'type' => 'book',
            'prompt' => 'Quero algo fora da minha zona de conforto',
            'use_history' => false,
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.use_history', false);

        $this->assertDatabaseHas('suggestions', [
            'user_id' => $this->user->id,
            'type' => 'book',
            'use_history' => false,
        ]);

        Http::assertSent(function ($request) {
            $userContent = $request['messages'][1]['content'];
            $systemContent = $request['messages'][0]['content'];
            return str_contains($systemContent, 'NÃO usar seu histórico de avaliações')
                && !str_contains($userContent, 'Histórico de avaliações da estante');
        });
    }

    public function test_prompt_instructs_titles_in_portuguese(): void
    {
        $this->mockOpenRouterSuccess([
            [
                'title' => 'O Poderoso Chefão',
                'creator' => 'Francis Ford Coppola',
                'year' => 1972,
                'synopsis' => 'Um épico da máfia.',
                'reason' => 'Grande clássico do cinema.',
            ],
        ]);

        $res = $this->postJson('/api/v1/suggestions', [
            'type' => 'movie',
            'prompt' => 'Filmes clássicos de máfia',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.use_history', true);

        $this->assertDatabaseHas('suggestions', [
            'user_id' => $this->user->id,
            'type' => 'movie',
            'use_history' => true,
        ]);

        Http::assertSent(function ($request) {
            $systemContent = $request['messages'][0]['content'];
            $userContent = $request['messages'][1]['content'];

            return str_contains($systemContent, 'português (título oficial')
                && str_contains($userContent, 'português (título oficial de lançamento no Brasil)');
        });
    }

    public function test_previously_suggested_titles_are_passed_to_prevent_repeating(): void
    {
        Http::fake([
            '*openrouter.ai/*' => Http::sequence()
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode(['suggestions' => [
                                    [
                                        'title' => 'Duna Especial',
                                        'creator' => 'Frank Herbert',
                                        'year' => 1965,
                                        'synopsis' => 'Sinopse',
                                        'reason' => 'Motivo',
                                    ],
                                ]]),
                            ],
                        ],
                    ],
                ])
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode(['suggestions' => [
                                    [
                                        'title' => 'Hyperion',
                                        'creator' => 'Dan Simmons',
                                        'year' => 1989,
                                        'synopsis' => 'Sinopse',
                                        'reason' => 'Motivo',
                                    ],
                                ]]),
                            ],
                        ],
                    ],
                ]),
        ]);

        // First generation
        $this->postJson('/api/v1/suggestions', [
            'type' => 'book',
            'prompt' => 'Primeiro pedido',
        ])->assertStatus(201);

        // Second generation should include 'Duna Especial' in the list of titles to avoid
        $this->postJson('/api/v1/suggestions', [
            'type' => 'book',
            'prompt' => 'Segundo pedido',
        ])->assertStatus(201);

        Http::assertSent(function ($request) {
            $userContent = $request['messages'][1]['content'];
            return str_contains($userContent, 'Duna Especial');
        });
    }

    public function test_show_latest_defaults_to_movie_when_type_omitted(): void
    {
        Suggestion::create([
            'user_id' => $this->user->id,
            'type' => 'movie',
            'prompt' => 'Filmes de ficção científica',
            'model' => 'google/gemini-2.5-flash',
        ]);

        $response = $this->getJson('/api/v1/suggestions');

        $response->assertOk()
            ->assertJsonPath('data.type', 'movie')
            ->assertJsonPath('data.prompt', 'Filmes de ficção científica');
    }

    public function test_can_generate_with_streaming_services_and_includes_them_in_prompt(): void
    {
        $this->mockOpenRouterSuccess([
            [
                'title' => 'Interestelar',
                'creator' => 'Christopher Nolan',
                'year' => 2014,
                'synopsis' => 'Viagem pelo espaço para salvar a humanidade.',
                'reason' => 'Disponível na Max e Prime Video.',
            ],
        ]);

        $response = $this->postJson('/api/v1/suggestions', [
            'type' => 'movie',
            'prompt' => 'Filme de exploração espacial',
            'streaming_services' => ['Netflix', 'Max'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.streaming_services', ['Netflix', 'Max'])
            ->assertJsonPath('data.model', 'google/gemini-3.5-flash');

        Http::assertSent(function ($request) {
            $systemContent = $request['messages'][0]['content'];
            $userContent = $request['messages'][1]['content'];

            return str_contains($systemContent, 'Netflix, Max')
                && str_contains($userContent, 'Netflix, Max')
                && str_contains($systemContent, 'Gere exatamente 10 recomendações');
        });
    }

    public function test_can_reject_suggested_item_and_saves_to_cache(): void
    {
        $suggestion = Suggestion::create([
            'user_id' => $this->user->id,
            'type' => 'movie',
            'prompt' => 'Filmes de ação',
            'model' => 'google/gemini-3.5-flash',
        ]);

        $media = $suggestion->media()->create([
            'position' => 1,
            'title' => 'Matrix',
            'creator' => 'Wachowskis',
            'year' => 1999,
        ]);

        $response = $this->postJson("/api/v1/suggested-media/{$media->id}/reject");

        $response->assertOk()
            ->assertJsonPath('message', "Item 'Matrix' rejeitado e adicionado ao cache de exclusão.");

        $this->assertDatabaseMissing('suggested_media', ['id' => $media->id]);
        $this->assertDatabaseHas('rejected_suggestions', [
            'user_id' => $this->user->id,
            'type' => 'movie',
            'title' => 'Matrix',
        ]);
    }

    public function test_user_cannot_reject_item_belonging_to_another_user(): void
    {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $suggestion = Suggestion::create([
            'type' => 'movie',
            'prompt' => 'Filmes de terror',
            'model' => 'google/gemini-3.5-flash',
        ]);

        $media = $suggestion->media()->create([
            'position' => 1,
            'title' => 'O Iluminado',
            'creator' => 'Stanley Kubrick',
            'year' => 1980,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/suggested-media/{$media->id}/reject");

        $response->assertStatus(404);
        $this->assertDatabaseHas('suggested_media', ['id' => $media->id]);
    }

    public function test_regenerating_suggestions_automatically_caches_unimported_items_as_rejected(): void
    {
        $suggestion = Suggestion::create([
            'user_id' => $this->user->id,
            'type' => 'movie',
            'prompt' => 'Primeira lista',
            'model' => 'google/gemini-3.5-flash',
        ]);

        $suggestion->media()->create([
            'position' => 1,
            'title' => 'Filme Descartado 1',
            'creator' => 'Diretor A',
            'year' => 2020,
        ]);

        $suggestion->media()->create([
            'position' => 2,
            'title' => 'Filme Descartado 2',
            'creator' => 'Diretor B',
            'year' => 2021,
        ]);

        $this->mockOpenRouterSuccess([
            [
                'title' => 'Novo Filme 1',
                'creator' => 'Diretor C',
                'year' => 2022,
                'synopsis' => 'Sinopse',
                'reason' => 'Motivo',
            ],
        ]);

        $this->postJson('/api/v1/suggestions', [
            'type' => 'movie',
            'prompt' => 'Segunda lista',
        ])->assertStatus(201);

        $this->assertDatabaseHas('rejected_suggestions', [
            'user_id' => $this->user->id,
            'type' => 'movie',
            'title' => 'Filme Descartado 1',
        ]);
        $this->assertDatabaseHas('rejected_suggestions', [
            'user_id' => $this->user->id,
            'type' => 'movie',
            'title' => 'Filme Descartado 2',
        ]);
    }

    public function test_rejected_suggestions_cache_caps_at_thirty_most_recent_items(): void
    {
        // Seed 35 rejected movies with explicit timestamps
        for ($i = 1; $i <= 35; $i++) {
            $record = new RejectedSuggestion([
                'type' => 'movie',
                'title' => "Filme Antigo {$i}",
                'normalized_title' => "filmeantigo{$i}",
            ]);
            $record->user_id = $this->user->id;
            $record->timestamps = false;
            $record->created_at = now()->subMinutes(40 - $i);
            $record->updated_at = now()->subMinutes(40 - $i);
            $record->save();
        }

        $this->mockOpenRouterSuccess([
            [
                'title' => 'Blade Runner 2049',
                'creator' => 'Denis Villeneuve',
                'year' => 2017,
                'synopsis' => 'Ficção cyberpunk.',
                'reason' => 'Excelente fotografia.',
            ],
        ]);

        $suggestion = Suggestion::create([
            'user_id' => $this->user->id,
            'type' => 'movie',
            'prompt' => 'Sci-fi',
            'model' => 'google/gemini-3.5-flash',
        ]);

        $media = $suggestion->media()->create([
            'position' => 1,
            'title' => 'Novo Filme Rejeitado',
            'creator' => 'Diretor X',
            'year' => 2024,
        ]);

        // Reject item to trigger cache bounds
        $this->postJson("/api/v1/suggested-media/{$media->id}/reject")->assertOk();

        // Must cap at 30 items
        $count = RejectedSuggestion::where('user_id', $this->user->id)
            ->where('type', 'movie')
            ->count();

        $this->assertEquals(30, $count);

        // Oldest items (1 through 5) should be pruned
        $this->assertDatabaseMissing('rejected_suggestions', [
            'user_id' => $this->user->id,
            'type' => 'movie',
            'title' => 'Filme Antigo 1',
        ]);
        $this->assertDatabaseMissing('rejected_suggestions', [
            'user_id' => $this->user->id,
            'type' => 'movie',
            'title' => 'Filme Antigo 5',
        ]);

        // Latest items and newly rejected item must be present
        $this->assertDatabaseHas('rejected_suggestions', [
            'user_id' => $this->user->id,
            'type' => 'movie',
            'title' => 'Filme Antigo 35',
        ]);
        $this->assertDatabaseHas('rejected_suggestions', [
            'user_id' => $this->user->id,
            'type' => 'movie',
            'title' => 'Novo Filme Rejeitado',
        ]);
    }
}
