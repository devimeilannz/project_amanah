<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['amanah.otp.expose_in_response' => true]);
        User::factory()->create([
            'email' => 'budi@gmail.com', 'whatsapp_number' => '+6281234567890', 'password' => 'Secret123!',
        ]);
    }

    private function resetFlow(array $identity): array
    {
        $otp = $this->postJson('/api/v1/auth/forgot-password', $identity)
            ->assertOk()->json('data.debug_otp');

        $token = $this->postJson('/api/v1/auth/forgot-password/verify-otp', $identity + ['otp' => $otp])
            ->assertOk()->json('data.reset_token');

        return [$otp, $token];
    }

    public function test_reset_password_via_email_otp(): void
    {
        $user = User::first();
        $user->createToken('old'); // token lama harus dicabut

        [, $token] = $this->resetFlow(['channel' => 'email', 'email' => 'budi@gmail.com']);

        $this->postJson('/api/v1/auth/reset-password', [
            'reset_token' => $token, 'password' => 'NewSecret456!', 'password_confirmation' => 'NewSecret456!',
        ])->assertOk()->assertJsonStructure(['data' => ['access_token']]);

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecret456!', $user->password));
        $this->assertSame(1, $user->tokens()->count()); // hanya token baru hasil auto-login

        $this->postJson('/api/v1/auth/login', ['email' => 'budi@gmail.com', 'password' => 'NewSecret456!'])->assertOk();
    }

    public function test_reset_password_via_whatsapp_otp(): void
    {
        [, $token] = $this->resetFlow(['channel' => 'whatsapp', 'whatsapp_number' => '0812-3456-7890']);

        $this->postJson('/api/v1/auth/reset-password', [
            'reset_token' => $token, 'password' => 'NewSecret456!', 'password_confirmation' => 'NewSecret456!',
        ])->assertOk();
    }

    public function test_reset_token_is_single_use(): void
    {
        [, $token] = $this->resetFlow(['channel' => 'email', 'email' => 'budi@gmail.com']);
        $body = ['reset_token' => $token, 'password' => 'NewSecret456!', 'password_confirmation' => 'NewSecret456!'];

        $this->postJson('/api/v1/auth/reset-password', $body)->assertOk();
        $this->postJson('/api/v1/auth/reset-password', $body)
            ->assertStatus(422)->assertJsonPath('code', 'RESET_TOKEN_INVALID');
    }

    public function test_reset_token_expires(): void
    {
        [, $token] = $this->resetFlow(['channel' => 'email', 'email' => 'budi@gmail.com']);
        $this->travel(11)->minutes();

        $this->postJson('/api/v1/auth/reset-password', [
            'reset_token' => $token, 'password' => 'NewSecret456!', 'password_confirmation' => 'NewSecret456!',
        ])->assertStatus(422)->assertJsonPath('code', 'RESET_TOKEN_INVALID');
    }

    public function test_wrong_and_expired_otp_rejected(): void
    {
        $identity = ['channel' => 'email', 'email' => 'budi@gmail.com'];
        $otp = $this->postJson('/api/v1/auth/forgot-password', $identity)->json('data.debug_otp');
        $wrong = $otp === '000000' ? '111111' : '000000';

        $this->postJson('/api/v1/auth/forgot-password/verify-otp', $identity + ['otp' => $wrong])
            ->assertStatus(422)->assertJsonPath('code', 'OTP_INVALID');

        $this->travel(6)->minutes();
        $this->postJson('/api/v1/auth/forgot-password/verify-otp', $identity + ['otp' => $otp])
            ->assertStatus(422)->assertJsonPath('code', 'OTP_EXPIRED');
    }

    public function test_unknown_account_gets_generic_response_without_otp(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['channel' => 'email', 'email' => 'nobody@gmail.com'])
            ->assertOk()->assertJsonMissingPath('data.debug_otp');
        $this->assertDatabaseCount('otp_codes', 0);
    }

    public function test_weak_new_password_rejected_and_validation(): void
    {
        [, $token] = $this->resetFlow(['channel' => 'email', 'email' => 'budi@gmail.com']);

        $this->postJson('/api/v1/auth/reset-password', [
            'reset_token' => $token, 'password' => 'lemah', 'password_confirmation' => 'lemah',
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['password']]);

        $this->postJson('/api/v1/auth/forgot-password', ['channel' => 'sms'])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['channel']]);
    }
}
