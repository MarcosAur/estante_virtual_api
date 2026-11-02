<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MovieReviewApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Movie $movie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);

        $this->movie = Movie::create([
            'title' => 'O Poderoso Chefão',
            'director' => 'Francis Ford Coppola',
            'year' => 1972,
            'status' => 'watched',
        ]);
    }

    public function test_can_create_and_update_review_for_movie(): void
    {
        $response = $this->postJson("/api/v1/movies/{$this->movie->id}/review", [
            'rating' => 5.0,
            'title' => 'Clássico do cinema mundial',
            'body' => 'Atuações memoráveis de Marlon Brando e Al Pacino.',
            'has_spoiler' => false,
        ]);

        $response->assertSuccessful();
        $this->assertEquals(5.0, (float) $response->json('data.rating'));
        $response->assertJsonPath('data.title', 'Clássico do cinema mundial');

        // Update existing review (upsert)
        $updateResponse = $this->putJson("/api/v1/movies/{$this->movie->id}/review", [
            'rating' => 4.5,
            'title' => 'Grande clássico',
            'body' => 'Revisto hoje com nova perspectiva.',
            'has_spoiler' => true,
        ]);

        $updateResponse->assertSuccessful();
        $this->assertEquals(4.5, (float) $updateResponse->json('data.rating'));
        $updateResponse->assertJsonPath('data.title', 'Grande clássico');
        $this->assertTrue($updateResponse->json('data.has_spoiler'));

        $this->assertDatabaseCount('movie_reviews', 1);
    }

    public function test_can_delete_movie_review(): void
    {
        $this->postJson("/api/v1/movies/{$this->movie->id}/review", [
            'rating' => 4.0,
            'body' => 'Muito bom.',
        ])->assertSuccessful();

        $response = $this->deleteJson("/api/v1/movies/{$this->movie->id}/review");
        $response->assertStatus(200);

        $this->assertSoftDeleted('movie_reviews', ['movie_id' => $this->movie->id]);
    }

    public function test_can_list_movie_reviews(): void
    {
        $this->postJson("/api/v1/movies/{$this->movie->id}/review", [
            'rating' => 4.5,
            'body' => 'Excelente roteiro.',
        ])->assertSuccessful();

        $response = $this->getJson('/api/v1/movie-reviews');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.movie.title', 'O Poderoso Chefão');
    }
}
