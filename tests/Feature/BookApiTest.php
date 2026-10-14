<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_can_create_book_with_tags(): void
    {
        $tag = Tag::create(['name' => 'Ficção']);

        $response = $this->postJson('/api/v1/books', [
            'title' => 'Dom Casmurro',
            'author' => 'Machado de Assis',
            'isbn' => '9788572329385',
            'pages' => 256,
            'status' => 'reading',
            'current_page' => 45,
            'tag_ids' => [$tag->id],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'Dom Casmurro');
        $response->assertJsonPath('data.tags.0.name', 'Ficção');
        $response->assertJsonPath('data.progress_percentage', 18);
    }

    public function test_can_list_and_filter_books(): void
    {
        Book::create(['title' => 'Livro A', 'author' => 'Autor X', 'status' => 'reading', 'pages' => 100]);
        Book::create(['title' => 'Livro B', 'author' => 'Autor Y', 'status' => 'read', 'pages' => 200]);
        Book::create(['title' => 'Livro C', 'author' => 'Autor X', 'status' => 'want_to_read', 'pages' => 300]);

        $all = $this->getJson('/api/v1/books');
        $all->assertStatus(200);
        $all->assertJsonCount(3, 'data');

        $readingOnly = $this->getJson('/api/v1/books?status=reading');
        $readingOnly->assertStatus(200);
        $readingOnly->assertJsonCount(1, 'data');
        $readingOnly->assertJsonPath('data.0.title', 'Livro A');

        $search = $this->getJson('/api/v1/books?q=Livro B');
        $search->assertStatus(200);
        $search->assertJsonCount(1, 'data');
        $search->assertJsonPath('data.0.title', 'Livro B');
    }

    public function test_can_update_reading_progress_and_auto_complete(): void
    {
        $book = Book::create([
            'title' => 'O Pequeno Príncipe',
            'author' => 'Antoine de Saint-Exupéry',
            'status' => 'reading',
            'pages' => 100,
            'current_page' => 50,
        ]);

        $response = $this->patchJson("/api/v1/books/{$book->id}/progress", [
            'current_page' => 100,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.current_page', 100);
        $response->assertJsonPath('data.status', 'read');
        $this->assertNotNull($response->json('data.finished_at'));
    }

    public function test_can_soft_delete_book(): void
    {
        $book = Book::create([
            'title' => '1984',
            'author' => 'George Orwell',
            'status' => 'want_to_read',
        ]);

        $response = $this->deleteJson("/api/v1/books/{$book->id}");
        $response->assertStatus(200);

        $this->assertSoftDeleted('books', ['id' => $book->id]);
        $this->getJson("/api/v1/books/{$book->id}")->assertStatus(404);
    }

    public function test_can_create_book_with_cover_photo(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->image('capa.png', 400, 600)->size(4000); // 4MB PNG

        $response = $this->postJson('/api/v1/books', [
            'title' => 'Livro com Capa',
            'author' => 'Autor Foto',
            'status' => 'want_to_read',
            'cover' => $file,
        ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('data.cover_url'));
        $coverPath = $response->json('data.cover_path');
        $this->assertNotNull($coverPath);
        Storage::disk('local')->assertExists($coverPath);
    }

    public function test_cannot_upload_cover_larger_than_5mb(): void
    {
        Storage::fake('local');
        $largeFile = UploadedFile::fake()->image('capa_grande.jpg')->size(5200); // 5.2MB (> 5MB)

        $response = $this->postJson('/api/v1/books', [
            'title' => 'Livro Inválido',
            'author' => 'Autor',
            'status' => 'want_to_read',
            'cover' => $largeFile,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cover']);
    }

    public function test_cannot_upload_invalid_file_format(): void
    {
        Storage::fake('local');
        $txtFile = UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/v1/books', [
            'title' => 'Livro PDF',
            'author' => 'Autor',
            'status' => 'want_to_read',
            'cover' => $txtFile,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cover']);
    }

    public function test_can_delete_book_cover(): void
    {
        Storage::fake('local');
        $book = Book::create([
            'title' => 'Livro com Foto',
            'author' => 'Autor',
            'status' => 'reading',
        ]);

        $file = UploadedFile::fake()->image('capa.jpeg');
        $this->postJson("/api/v1/books/{$book->id}/cover", ['cover' => $file])
            ->assertStatus(200);

        $book->refresh();
        $this->assertNotNull($book->cover_path);

        $response = $this->deleteJson("/api/v1/books/{$book->id}/cover");
        $response->assertStatus(200);
        $response->assertJsonPath('data.cover_url', null);

        $book->refresh();
        $this->assertNull($book->cover_path);
    }

    public function test_reading_goals_routes_are_not_found(): void
    {
        $this->getJson('/api/v1/goals/2026')->assertStatus(404);
        $this->putJson('/api/v1/goals/2026', ['target' => 20])->assertStatus(404);
    }

    public function test_can_disable_user_registration_via_env(): void
    {
        config(['auth.registration_enabled' => false]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Novo Usuário',
            'email' => 'novo@teste.com',
            'password' => 'senhaSegura123',
            'password_confirmation' => 'senhaSegura123',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Novos cadastros estão temporariamente desativados no sistema.']);

        config(['auth.registration_enabled' => true]);

        $responseSuccess = $this->postJson('/api/v1/auth/register', [
            'name' => 'Novo Usuário',
            'email' => 'novo@teste.com',
            'password' => 'senhaSegura123',
            'password_confirmation' => 'senhaSegura123',
        ]);

        $responseSuccess->assertStatus(201);
    }
}
