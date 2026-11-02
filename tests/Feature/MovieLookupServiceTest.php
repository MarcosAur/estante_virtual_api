<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MovieLookupService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MovieLookupServiceTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
        Cache::flush();
    }

    public function test_returns_empty_when_no_api_key_configured(): void
    {
        config(['services.tmdb.api_key' => '']);

        $service = new MovieLookupService();
        $results = $service->search('Matrix');

        $this->assertEmpty($results);
    }

    public function test_can_search_movies_via_tmdb_api(): void
    {
        config(['services.tmdb.api_key' => 'fake_api_key']);

        Http::fake([
            'https://api.themoviedb.org/3/search/movie*' => Http::response([
                'results' => [
                    [
                        'id' => 603,
                        'title' => 'The Matrix',
                        'release_date' => '1999-03-30',
                        'overview' => 'Um programador descobre a verdadeira realidade.',
                        'genre_ids' => [28, 878],
                        'poster_path' => '/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg',
                    ],
                ],
            ], 200),
        ]);

        $service = new MovieLookupService();
        $results = $service->search('Matrix');

        $this->assertCount(1, $results);
        $this->assertEquals('The Matrix', $results[0]['title']);
        $this->assertEquals(1999, $results[0]['year']);
        $this->assertEquals('603', $results[0]['tmdb_id']);
        $this->assertEquals('Ação, Ficção científica', $results[0]['genre']);
        $this->assertEquals('https://image.tmdb.org/t/p/w500/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg', $results[0]['poster_url']);
    }

    public function test_handles_api_failure_gracefully(): void
    {
        config(['services.tmdb.api_key' => 'fake_api_key']);

        Http::fake([
            'https://api.themoviedb.org/3/search/movie*' => Http::response(['status_message' => 'Invalid API key'], 401),
        ]);

        $service = new MovieLookupService();
        $results = $service->search('Interstellar');

        $this->assertEmpty($results);
    }

    public function test_lookup_endpoint_returns_json(): void
    {
        config(['services.tmdb.api_key' => 'fake_api_key']);

        Http::fake([
            'https://api.themoviedb.org/3/search/movie*' => Http::response([
                'results' => [
                    [
                        'id' => 157336,
                        'title' => 'Interstellar',
                        'release_date' => '2014-11-05',
                        'overview' => 'Explorando o espaço.',
                        'genre_ids' => [878, 18],
                        'poster_path' => '/gEU2QniE6EwfVDxCzsEUHtvy2dn.jpg',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/v1/lookup/movies?q=Interstellar');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Interstellar');
    }
}
