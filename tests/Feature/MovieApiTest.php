<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MovieApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_can_create_movie_with_tags(): void
    {
        $tag = Tag::create(['name' => 'Sci-Fi']);

        $response = $this->postJson('/api/v1/movies', [
            'title' => 'Interestelar',
            'director' => 'Christopher Nolan',
            'year' => 2014,
            'runtime_minutes' => 169,
            'genre' => 'Ficção Científica',
            'synopsis' => 'Uma viagem espacial para salvar a humanidade.',
            'status' => 'watched',
            'tag_ids' => [$tag->id],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'Interestelar');
        $response->assertJsonPath('data.director', 'Christopher Nolan');
        $response->assertJsonPath('data.runtime_minutes', 169);
        $response->assertJsonPath('data.tags.0.name', 'Sci-Fi');
        $this->assertNotNull($response->json('data.watched_at'));
    }

    public function test_can_list_and_filter_movies(): void
    {
        $tag = Tag::create(['name' => 'Ação']);

        $m1 = Movie::create([
            'title' => 'Matrix',
            'director' => 'Lana & Lilly Wachowski',
            'year' => 1999,
            'status' => 'watched',
        ]);
        $m1->tags()->attach($tag);

        Movie::create([
            'title' => 'Duna: Parte 2',
            'director' => 'Denis Villeneuve',
            'year' => 2024,
            'status' => 'want_to_watch',
        ]);

        // Filter by status
        $resStatus = $this->getJson('/api/v1/movies?status=watched');
        $resStatus->assertStatus(200);
        $resStatus->assertJsonCount(1, 'data');
        $resStatus->assertJsonPath('data.0.title', 'Matrix');

        // Search by query
        $resSearch = $this->getJson('/api/v1/movies?q=Duna');
        $resSearch->assertStatus(200);
        $resSearch->assertJsonCount(1, 'data');
        $resSearch->assertJsonPath('data.0.title', 'Duna: Parte 2');

        // Filter by tag
        $resTag = $this->getJson('/api/v1/movies?tag=Ação');
        $resTag->assertStatus(200);
        $resTag->assertJsonCount(1, 'data');
        $resTag->assertJsonPath('data.0.title', 'Matrix');
    }

    public function test_can_update_movie_status(): void
    {
        $movie = Movie::create([
            'title' => 'Oppenheimer',
            'director' => 'Christopher Nolan',
            'status' => 'want_to_watch',
        ]);

        $response = $this->patchJson("/api/v1/movies/{$movie->id}/status", [
            'status' => 'watched',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'watched');
        $this->assertNotNull($response->json('data.watched_at'));
    }

    public function test_can_soft_delete_movie(): void
    {
        $movie = Movie::create([
            'title' => 'Filme para Apagar',
            'status' => 'want_to_watch',
        ]);

        $response = $this->deleteJson("/api/v1/movies/{$movie->id}");
        $response->assertStatus(200);

        $this->assertSoftDeleted('movies', ['id' => $movie->id]);
    }

    public function test_can_create_movie_with_poster_photo(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->image('poster.jpg', 600, 900);

        $response = $this->postJson('/api/v1/movies', [
            'title' => 'Blade Runner 2049',
            'director' => 'Denis Villeneuve',
            'status' => 'watched',
            'poster' => $file,
        ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('data.poster_url'));

        $movie = Movie::first();
        $this->assertNotNull($movie->poster_path);
        Storage::disk('local')->assertExists($movie->poster_path);
    }

    public function test_cannot_upload_poster_larger_than_5mb(): void
    {
        Storage::fake('local');
        $movie = Movie::create([
            'title' => 'Filme Pesado',
            'status' => 'want_to_watch',
        ]);

        $file = UploadedFile::fake()->create('poster_grande.jpg', 6000, 'image/jpeg');

        $response = $this->postJson("/api/v1/movies/{$movie->id}/poster", [
            'poster' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['poster']);
    }

    public function test_cannot_upload_invalid_poster_format(): void
    {
        Storage::fake('local');
        $movie = Movie::create([
            'title' => 'Filme PDF',
            'status' => 'want_to_watch',
        ]);

        $file = UploadedFile::fake()->create('documento.pdf', 500, 'application/pdf');

        $response = $this->postJson("/api/v1/movies/{$movie->id}/poster", [
            'poster' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['poster']);
    }

    public function test_can_delete_movie_poster(): void
    {
        Storage::fake('local');
        $movie = Movie::create([
            'title' => 'Filme com Poster',
            'status' => 'watching',
        ]);

        $file = UploadedFile::fake()->image('poster.png');
        $this->postJson("/api/v1/movies/{$movie->id}/poster", ['poster' => $file])
            ->assertStatus(200);

        $movie->refresh();
        $this->assertNotNull($movie->poster_path);

        $response = $this->deleteJson("/api/v1/movies/{$movie->id}/poster");
        $response->assertStatus(200);
        $response->assertJsonPath('data.poster_url', null);

        $movie->refresh();
        $this->assertNull($movie->poster_path);
    }
}
