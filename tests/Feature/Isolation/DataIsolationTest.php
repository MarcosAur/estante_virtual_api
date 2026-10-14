<?php

namespace Tests\Feature\Isolation;

use App\Models\Book;
use App\Models\Review;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DataIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private User $userB;
    private Book $bookA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::factory()->create([
            'name' => 'User A',
            'email' => 'user_a@example.com',
        ]);

        $this->userB = User::factory()->create([
            'name' => 'User B',
            'email' => 'user_b@example.com',
        ]);

        // Create resource as User A
        Sanctum::actingAs($this->userA);
        $this->bookA = Book::create([
            'title' => 'Livro Secreto do Usuario A',
            'author' => 'Autor Secreto',
            'status' => 'reading',
            'pages' => 350,
            'current_page' => 120,
        ]);
    }

    public function test_user_b_cannot_view_user_a_book_and_gets_404(): void
    {
        Sanctum::actingAs($this->userB);

        $response = $this->getJson("/api/v1/books/{$this->bookA->id}");

        $response->assertStatus(404);
    }

    public function test_user_b_does_not_see_user_a_book_in_listings_or_search(): void
    {
        Sanctum::actingAs($this->userB);

        $response = $this->getJson('/api/v1/books');
        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');

        $searchResponse = $this->getJson('/api/v1/books?q=Secreto');
        $searchResponse->assertStatus(200);
        $searchResponse->assertJsonCount(0, 'data');
    }

    public function test_user_b_cannot_update_user_a_book(): void
    {
        Sanctum::actingAs($this->userB);

        $response = $this->putJson("/api/v1/books/{$this->bookA->id}", [
            'title' => 'Tentativa de Hack',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseHas('books', [
            'id' => $this->bookA->id,
            'title' => 'Livro Secreto do Usuario A',
        ]);
    }

    public function test_user_b_cannot_delete_user_a_book(): void
    {
        Sanctum::actingAs($this->userB);

        $response = $this->deleteJson("/api/v1/books/{$this->bookA->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('books', [
            'id' => $this->bookA->id,
            'deleted_at' => null,
        ]);
    }

    public function test_user_b_cannot_update_progress_of_user_a_book(): void
    {
        Sanctum::actingAs($this->userB);

        $response = $this->patchJson("/api/v1/books/{$this->bookA->id}/progress", [
            'current_page' => 200,
        ]);

        $response->assertStatus(404);
    }

    public function test_user_b_cannot_access_user_a_cover(): void
    {
        Storage::fake('local');

        Sanctum::actingAs($this->userA);
        $file = UploadedFile::fake()->image('capa.jpg');
        $this->postJson("/api/v1/books/{$this->bookA->id}/cover", ['cover' => $file])
            ->assertStatus(200);

        Sanctum::actingAs($this->userB);
        $response = $this->getJson("/api/v1/books/{$this->bookA->id}/cover");
        $response->assertStatus(404);
    }

    public function test_user_b_cannot_see_user_a_reviews(): void
    {
        Sanctum::actingAs($this->userA);
        $this->postJson("/api/v1/books/{$this->bookA->id}/review", [
            'rating' => 4.5,
            'title' => 'Review confidencial',
            'body' => 'Muito bom!',
        ])->assertSuccessful();

        Sanctum::actingAs($this->userB);

        $this->getJson("/api/v1/books/{$this->bookA->id}/review")->assertStatus(404);

        $response = $this->getJson('/api/v1/reviews');
        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_user_b_cannot_see_or_delete_user_a_tags(): void
    {
        Sanctum::actingAs($this->userA);
        $tag = Tag::create(['name' => 'Ficção Científica']);

        Sanctum::actingAs($this->userB);
        $response = $this->getJson('/api/v1/tags');
        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');

        $this->deleteJson("/api/v1/tags/{$tag->id}")->assertStatus(404);
    }

    public function test_user_cannot_force_user_id_in_creation(): void
    {
        Sanctum::actingAs($this->userB);

        $response = $this->postJson('/api/v1/books', [
            'title' => 'Livro Criado pelo User B',
            'author' => 'Autor B',
            'status' => 'want_to_read',
            'user_id' => $this->userA->id,
        ]);

        $response->assertStatus(201);
        $bookId = $response->json('data.id');

        $this->assertDatabaseHas('books', [
            'id' => $bookId,
            'user_id' => $this->userB->id,
        ]);
        $this->assertDatabaseMissing('books', [
            'id' => $bookId,
            'user_id' => $this->userA->id,
        ]);
    }

    public function test_user_cannot_attach_other_users_tags(): void
    {
        Sanctum::actingAs($this->userA);
        $tagA = Tag::create(['name' => 'Tag Privada A']);

        Sanctum::actingAs($this->userB);
        $response = $this->postJson('/api/v1/books', [
            'title' => 'Livro do User B',
            'author' => 'Autor B',
            'status' => 'want_to_read',
            'tag_ids' => [$tagA->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tag_ids.0']);
    }

    public function test_user_b_stats_do_not_include_user_a_data(): void
    {
        Sanctum::actingAs($this->userA);
        Book::create([
            'title' => 'Livro Lido A',
            'author' => 'Autor A',
            'status' => 'read',
            'pages' => 320,
            'finished_at' => now()->toDateString(),
        ]);

        Sanctum::actingAs($this->userB);
        $response = $this->getJson('/api/v1/stats');
        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'total_books' => 0,
                'read_this_year' => 0,
                'pages_read_this_year' => 0,
            ],
        ]);
        $response->assertJsonMissing(['reading_goal']);
    }
}
