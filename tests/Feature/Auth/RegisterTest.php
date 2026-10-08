<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $override = []): array
    {
        return array_merge([
            'name'                  => 'Budi Santoso',
            'email'                 => 'Budi.Santoso@Gmail.com',
            'whatsapp_number'       => '+62 812-3456-7890',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'terms_accepted'        => true,
        ], $override);
    }

    public function test_user_can_register_and_password_is_hashed(): void
    {
        $res = $this->postJson('/api/v1/auth/register', $this->payload());

        $res->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'budi.santoso@gmail.com')
            ->assertJsonPath('data.user.whatsapp_number', '+6281234567890')
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'expires_at']]);

        $user = User::firstOrFail();
        $this->assertNotSame('Secret123!', $user->password);
        $this->assertTrue(Hash::check('Secret123!', $user->password));
    }

    public function test_duplicate_email_and_whatsapp_are_rejected(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload())->assertCreated();

        $this->postJson('/api/v1/auth/register', $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors(['email', 'whatsapp_number'], 'errors');
    }

    public function test_weak_password_and_missing_terms_are_rejected(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload([
            'password' => 'abc', 'password_confirmation' => 'abc', 'terms_accepted' => false,
        ]))->assertStatus(422)->assertJsonStructure(['errors' => ['password', 'terms_accepted']]);
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(['password_confirmation' => 'Other123!']))
            ->assertStatus(422)->assertJsonStructure(['errors' => ['password']]);
    }

    public function test_check_availability_endpoint(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload())->assertCreated();

        $this->postJson('/api/v1/auth/check-availability', ['email' => 'budi.santoso@gmail.com'])
            ->assertOk()->assertJsonPath('data.email.available', false);
        $this->postJson('/api/v1/auth/check-availability', ['email' => 'baru@gmail.com'])
            ->assertOk()->assertJsonPath('data.email.available', true);
    }
}
