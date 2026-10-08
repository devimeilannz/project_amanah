<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create([
            'email'           => 'budi.santoso@domain.id',
            'whatsapp_number' => '+6281234567890',
            'password'        => 'Secret123!',
        ]);
    }

    public function test_login_success_returns_token_and_me_works(): void
    {
        $this->makeUser();

        $res = $this->postJson('/api/v1/auth/login', [
            'email' => 'budi.santoso@domain.id', 'password' => 'Secret123!', 'remember' => true,
        ])->assertOk()->assertJsonPath('success', true);

        $token = $res->json('data.access_token');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()->assertJsonPath('data.user.email', 'budi.santoso@domain.id');
    }

    public function test_wrong_password_returns_401_with_remaining_attempts(): void
    {
        $this->makeUser();

        $this->postJson('/api/v1/auth/login', ['email' => 'budi.santoso@domain.id', 'password' => 'salah'])
            ->assertStatus(401)
            ->assertJsonPath('code', 'AUTH_FAILED')
            ->assertJsonPath('remaining_attempts', 4);
    }

    public function test_unknown_email_returns_same_401(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'x@domain.id', 'password' => 'salah'])
            ->assertStatus(401)->assertJsonPath('code', 'AUTH_FAILED');
    }

    public function test_account_locks_after_max_failed_attempts(): void
    {
        $this->makeUser();
        $bad = ['email' => 'budi.santoso@domain.id', 'password' => 'salah'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', $bad)->assertStatus(401);
        }

        // Benar pun ditolak saat terkunci
        $this->postJson('/api/v1/auth/login', ['email' => 'budi.santoso@domain.id', 'password' => 'Secret123!'])
            ->assertStatus(423)->assertJsonPath('code', 'ACCOUNT_LOCKED');
    }

    public function test_protected_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_logout_revokes_token(): void
    {
        $this->makeUser();
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'budi.santoso@domain.id', 'password' => 'Secret123!',
        ])->json('data.access_token');

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unknown_route_returns_json_404(): void
    {
        $this->getJson('/api/v1/tidak-ada')->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
    }
}
