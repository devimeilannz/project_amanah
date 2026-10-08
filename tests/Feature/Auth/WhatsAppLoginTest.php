<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppLoginTest extends TestCase
{
    use RefreshDatabase;

    private const NUMBER = '+6281234567890';

    protected function setUp(): void
    {
        parent::setUp();
        config(['amanah.otp.expose_in_response' => true]);
        User::factory()->create(['whatsapp_number' => self::NUMBER, 'email' => 'budi@domain.id']);
    }

    private function requestOtp(): string
    {
        return $this->postJson('/api/v1/auth/login/whatsapp/request-otp', ['whatsapp_number' => '812-3456-7890'])
            ->assertOk()->json('data.debug_otp');
    }

    private function verify(string $otp)
    {
        return $this->postJson('/api/v1/auth/login/whatsapp/verify-otp', [
            'whatsapp_number' => self::NUMBER, 'otp' => $otp,
        ]);
    }

    public function test_full_whatsapp_login_flow(): void
    {
        $otp = $this->requestOtp();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);

        $this->postJson('/api/v1/auth/login/whatsapp/request-otp', ['whatsapp_number' => self::NUMBER])
            ->assertStatus(429)->assertJsonPath('code', 'OTP_COOLDOWN'); // cooldown resend

        $this->verify($otp)->assertOk()->assertJsonStructure(['data' => ['access_token']]);
        $this->assertNotNull(User::first()->whatsapp_verified_at);
        $this->assertDatabaseMissing('otp_codes', ['code_hash' => $otp]); // OTP tidak disimpan plaintext
    }

    public function test_unregistered_number_returns_404(): void
    {
        $this->postJson('/api/v1/auth/login/whatsapp/request-otp', ['whatsapp_number' => '+6285500000000'])
            ->assertStatus(404)->assertJsonPath('code', 'USER_NOT_FOUND');
    }

    public function test_wrong_otp_is_rejected_with_remaining_attempts(): void
    {
        $otp = $this->requestOtp();
        $wrong = $otp === '000000' ? '111111' : '000000';

        $this->verify($wrong)->assertStatus(422)
            ->assertJsonPath('code', 'OTP_INVALID')->assertJsonPath('remaining_attempts', 4);
    }

    public function test_expired_otp_is_rejected(): void
    {
        $otp = $this->requestOtp();
        $this->travel(6)->minutes();

        $this->verify($otp)->assertStatus(422)->assertJsonPath('code', 'OTP_EXPIRED');
    }

    public function test_otp_is_single_use(): void
    {
        $otp = $this->requestOtp();
        $this->verify($otp)->assertOk();
        $this->verify($otp)->assertStatus(422)->assertJsonPath('code', 'OTP_INVALID');
    }

    public function test_otp_locked_after_too_many_wrong_attempts(): void
    {
        $otp = $this->requestOtp();
        $wrong = $otp === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->verify($wrong)->assertStatus(422);
        }
        $this->verify($otp)->assertStatus(429)->assertJsonPath('code', 'OTP_ATTEMPTS_EXCEEDED');
    }

    public function test_otp_must_be_six_digits(): void
    {
        $this->verify('12ab')->assertStatus(422)->assertJsonStructure(['errors' => ['otp']]);
    }
}
