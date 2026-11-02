<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\User;
use App\Models\WritingEntry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WritingEntryApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_can_create_writing_entry_with_tags_and_source(): void
    {
        $tag = Tag::create(['name' => 'Bíblia']);

        $response = $this->postJson('/api/v1/writings', [
            'kind' => 'reading',
            'title' => 'Salmos 23',
            'body' => 'O Senhor é o meu pastor, nada me faltará.',
            'entry_date' => now()->toDateString(),
            'source_label' => 'Bíblia Sagrada',
            'mood' => 'paz',
            'tag_ids' => [$tag->id],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.kind', 'reading')
            ->assertJsonPath('data.title', 'Salmos 23')
            ->assertJsonPath('data.source_label', 'Bíblia Sagrada')
            ->assertJsonPath('data.mood', 'paz')
            ->assertJsonCount(1, 'data.tags');

        $this->assertDatabaseHas('writing_entries', [
            'user_id' => $this->user->id,
            'title' => 'Salmos 23',
            'kind' => 'reading',
        ]);
    }

    public function test_can_list_and_filter_writings(): void
    {
        WritingEntry::create([
            'kind' => 'reflection',
            'title' => 'Reflexão sobre paciência',
            'body' => 'A paciência é uma virtude essencial.',
            'entry_date' => '2026-11-01',
            'source_label' => 'Artigo de Filosofia',
        ]);

        WritingEntry::create([
            'kind' => 'reading',
            'title' => 'Gênesis 1',
            'body' => 'No princípio criou Deus os céus e a terra.',
            'entry_date' => '2026-11-02',
            'source_label' => 'Bíblia',
        ]);

        // Filter by kind
        $resKind = $this->getJson('/api/v1/writings?kind=reflection');
        $resKind->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Reflexão sobre paciência');

        // Search query
        $resSearch = $this->getJson('/api/v1/writings?q=Gênesis');
        $resSearch->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Gênesis 1');

        // Search in source_label
        $resSource = $this->getJson('/api/v1/writings?q=Filosofia');
        $resSource->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Reflexão sobre paciência');
    }

    public function test_can_show_and_update_writing_entry(): void
    {
        $entry = WritingEntry::create([
            'kind' => 'free',
            'body' => 'Texto inicial.',
            'entry_date' => '2026-11-02',
        ]);

        $showRes = $this->getJson("/api/v1/writings/{$entry->id}");
        $showRes->assertStatus(200)
            ->assertJsonPath('data.body', 'Texto inicial.');

        $updateRes = $this->putJson("/api/v1/writings/{$entry->id}", [
            'title' => 'Novo Título',
            'body' => 'Texto atualizado.',
            'mood' => 'esperança',
        ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.title', 'Novo Título')
            ->assertJsonPath('data.body', 'Texto atualizado.')
            ->assertJsonPath('data.mood', 'esperança');

        $this->assertDatabaseHas('writing_entries', [
            'id' => $entry->id,
            'title' => 'Novo Título',
            'body' => 'Texto atualizado.',
        ]);
    }

    public function test_can_soft_delete_writing_entry(): void
    {
        $entry = WritingEntry::create([
            'kind' => 'note',
            'body' => 'Anotação rápida',
            'entry_date' => '2026-11-02',
        ]);

        $deleteRes = $this->deleteJson("/api/v1/writings/{$entry->id}");
        $deleteRes->assertStatus(200);

        $this->assertSoftDeleted('writing_entries', ['id' => $entry->id]);

        $getRes = $this->getJson("/api/v1/writings/{$entry->id}");
        $getRes->assertStatus(404);
    }

    public function test_calculates_summary_and_streaks_correctly(): void
    {
        // Simulate today as 2026-11-04
        Carbon::setTestNow(Carbon::parse('2026-11-04 12:00:00'));

        // Entries: 2026-11-02, 2026-11-03, 2026-11-04 (today) -> 3 days streak
        // Also multiple entries on 2026-11-03 should count as 1 day
        WritingEntry::create([
            'kind' => 'reflection',
            'body' => 'Dia 2',
            'entry_date' => '2026-11-02',
        ]);
        WritingEntry::create([
            'kind' => 'reading',
            'body' => 'Dia 3 - entrada 1',
            'entry_date' => '2026-11-03',
        ]);
        WritingEntry::create([
            'kind' => 'note',
            'body' => 'Dia 3 - entrada 2',
            'entry_date' => '2026-11-03',
        ]);
        WritingEntry::create([
            'kind' => 'free',
            'body' => 'Hoje',
            'entry_date' => '2026-11-04',
        ]);

        $res = $this->getJson('/api/v1/writings/summary');
        $res->assertStatus(200)
            ->assertJsonPath('data.total', 4)
            ->assertJsonPath('data.by_kind.reflection', 1)
            ->assertJsonPath('data.by_kind.reading', 1)
            ->assertJsonPath('data.by_kind.note', 1)
            ->assertJsonPath('data.by_kind.free', 1)
            ->assertJsonPath('data.entries_this_month', 4)
            ->assertJsonPath('data.current_streak', 3)
            ->assertJsonPath('data.longest_streak', 3);

        Carbon::setTestNow(); // reset
    }

    public function test_user_b_cannot_access_or_modify_user_a_writing(): void
    {
        $entryA = WritingEntry::create([
            'kind' => 'reflection',
            'title' => 'Segredo do Usuário A',
            'body' => 'Minha reflexão secreta.',
            'entry_date' => '2026-11-02',
        ]);

        $userB = User::factory()->create();
        Sanctum::actingAs($userB);

        // Cannot view
        $this->getJson("/api/v1/writings/{$entryA->id}")->assertStatus(404);

        // Cannot update
        $this->putJson("/api/v1/writings/{$entryA->id}", [
            'body' => 'Hackeado',
        ])->assertStatus(404);

        // Cannot delete
        $this->deleteJson("/api/v1/writings/{$entryA->id}")->assertStatus(404);

        // Does not appear in listing
        $listRes = $this->getJson('/api/v1/writings');
        $listRes->assertJsonCount(0, 'data');

        // Summary of User B is empty
        $sumRes = $this->getJson('/api/v1/writings/summary');
        $sumRes->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.current_streak', 0);
    }

    public function test_validates_required_fields_and_enums(): void
    {
        $res = $this->postJson('/api/v1/writings', [
            'kind' => 'invalid_kind',
            'body' => '',
            'entry_date' => 'invalid_date',
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['kind', 'body', 'entry_date']);
    }
}
