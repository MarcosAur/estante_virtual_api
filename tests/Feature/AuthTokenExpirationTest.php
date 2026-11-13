<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthTokenExpirationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'email' => 'testuser@example.com',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_login_issues_token_with_2_hour_expiration(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'testuser@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'token',
                'expires_in',
            ]);

        $this->assertEquals(7200, $response->json('expires_in'));

        $tokenRecord = PersonalAccessToken::first();
        $this->assertNotNull($tokenRecord);
        $this->assertNotNull($tokenRecord->expires_at);

        // Difference in minutes between expires_at and created_at should be 120
        $diffInMinutes = $tokenRecord->created_at->diffInMinutes($tokenRecord->expires_at);
        $this->assertEquals(120, $diffInMinutes);
    }

    public function test_token_is_valid_within_expiration_window(): void
    {
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'testuser@example.com',
            'password' => 'password123',
        ]);

        $token = $loginRes->json('token');

        // Advance time by 1 hour (within the 2-hour window)
        $this->travel(1)->hours();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/me');

        $response->assertStatus(200)
            ->assertJsonPath('user.email', 'testuser@example.com');
    }

    public function test_expired_token_returns_401_unauthorized(): void
    {
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'testuser@example.com',
            'password' => 'password123',
        ]);

        $token = $loginRes->json('token');

        // Advance time past 2 hours (121 minutes)
        $this->travel(121)->minutes();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/me');

        $response->assertStatus(401);
    }

    public function test_refresh_route_rotates_token_and_extends_for_2_hours(): void
    {
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'testuser@example.com',
            'password' => 'password123',
        ]);

        $token1 = $loginRes->json('token');

        // Advance time by 90 minutes
        $this->travel(90)->minutes();

        $tokenRecord1 = PersonalAccessToken::first();
        $this->assertNotNull($tokenRecord1);

        $refreshRes = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson('/api/v1/auth/refresh');

        $refreshRes->assertStatus(200)
            ->assertJsonStructure(['user', 'token', 'expires_in'])
            ->assertJsonPath('expires_in', 7200);

        // Assert token1 is removed from database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenRecord1->id,
        ]);

        $token2 = $refreshRes->json('token');
        $this->assertNotEquals($token1, $token2);

        // In tests, reset guards between requests to simulate distinct HTTP cycles
        $this->app['auth']->forgetGuards();

        // Old token must now be invalid immediately
        $oldTokenRes = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->getJson('/api/v1/me');
        $oldTokenRes->assertStatus(401);

        $this->app['auth']->forgetGuards();

        // New token must work
        $newTokenRes = $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->getJson('/api/v1/me');
        $newTokenRes->assertStatus(200);

        // Advance another 110 minutes (total 200 minutes from start, but 110 from token2)
        $this->travel(110)->minutes();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->getJson('/api/v1/me')
            ->assertStatus(200);

        // Advance 15 more minutes (token2 expired)
        $this->travel(15)->minutes();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->getJson('/api/v1/me')
            ->assertStatus(401);
    }

    public function test_cannot_refresh_with_already_expired_token(): void
    {
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'testuser@example.com',
            'password' => 'password123',
        ]);

        $token = $loginRes->json('token');

        // Advance past expiration
        $this->travel(121)->minutes();

        $refreshRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/refresh');

        $refreshRes->assertStatus(401);
    }
}
