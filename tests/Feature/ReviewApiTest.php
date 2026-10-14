<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);

        $this->book = Book::create([
            'title' => 'Ensaio sobre a Cegueira',
            'author' => 'José Saramago',
            'pages' => 310,
            'status' => 'read',
        ]);
    }

    public function test_can_create_and_update_review_for_book(): void
    {
        $response = $this->postJson("/api/v1/books/{$this->book->id}/review", [
            'rating' => 4.5,
            'title' => 'Obra-prima',
            'body' => 'Uma reflexão profunda sobre a humanidade.',
            'has_spoiler' => false,
        ]);

        $response->assertSuccessful();
        $response->assertJsonPath('data.rating', 4.5);
        $response->assertJsonPath('data.title', 'Obra-prima');

        // Update existing review (upsert)
        $updateResponse = $this->putJson("/api/v1/books/{$this->book->id}/review", [
            'rating' => 5.0,
            'title' => 'Obra-prima Absoluta',
            'body' => 'Mudou minha percepção.',
            'has_spoiler' => false,
        ]);

        $updateResponse->assertSuccessful();
        $this->assertEquals(5.0, (float) $updateResponse->json('data.rating'));
        $updateResponse->assertJsonPath('data.title', 'Obra-prima Absoluta');

        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_can_delete_review(): void
    {
        $this->postJson("/api/v1/books/{$this->book->id}/review", [
            'rating' => 3.0,
            'body' => 'Legal.',
        ])->assertSuccessful();

        $response = $this->deleteJson("/api/v1/books/{$this->book->id}/review");
        $response->assertStatus(200);

        $this->assertSoftDeleted('reviews', ['book_id' => $this->book->id]);
    }
}
