<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Controllers\Concerns\IssuesTokens;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\VerifyResetOtpRequest;
use App\Models\PasswordResetSession;
use App\Models\User;
use App\Services\OtpService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    use IssuesTokens;

    public function __construct(private OtpService $otp)
    {
    }

    private function findUser(string $channel, string $destination): ?User
    {
        return User::where($channel === 'email' ? 'email' : 'whatsapp_number', $destination)->first();
    }

    /** Langkah 1: kirim OTP. Respons selalu sama agar tidak membocorkan apakah akun ada (anti-enumeration). */
    public function request(ForgotPasswordRequest $request): JsonResponse
    {
        $channel     = $request->validated('channel');
        $destination = $request->destination();
        $user        = $this->findUser($channel, $destination);
        $code        = null;

        if ($user) {
            [, $code] = $this->otp->issue('password_reset', $channel, $destination, $user, $request->ip());
        }

        return ApiResponse::success(
            $this->otp->payload($channel, $destination, $code),
            'Jika akun terdaftar, kode OTP telah dikirim.'
        );
    }

    /** Langkah 2: verifikasi OTP => reset_token sekali pakai. */
    public function verifyOtp(VerifyResetOtpRequest $request): JsonResponse
    {
        $channel     = $request->validated('channel');
        $destination = $request->destination();

        $this->otp->verify('password_reset', $channel, $destination, $request->validated('otp'));

        $user = $this->findUser($channel, $destination);
        if (! $user) {
            throw new ApiException('Kode OTP tidak valid. Silakan minta kode baru.', 'OTP_INVALID', 422);
        }

        if ($channel === 'email' && ! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }
        if ($channel === 'whatsapp' && ! $user->whatsapp_verified_at) {
            $user->forceFill(['whatsapp_verified_at' => now()])->save();
        }

        $ttl   = (int) config('amanah.reset_token_ttl_minutes');
        $plain = Str::random(64);

        PasswordResetSession::where('user_id', $user->id)->whereNull('used_at')->delete();
        PasswordResetSession::create([
            'user_id'    => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addMinutes($ttl),
            'ip_address' => $request->ip(),
        ]);

        return ApiResponse::success(
            ['reset_token' => $plain, 'expires_in' => $ttl * 60],
            'Verifikasi berhasil. Silakan atur kata sandi baru.'
        );
    }

    /** Langkah 3: set kata sandi baru, cabut semua token lama, login otomatis. */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $session = PasswordResetSession::where('token_hash', hash('sha256', $request->validated('reset_token')))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $session) {
            throw new ApiException('Token reset tidak valid atau sudah kedaluwarsa. Ulangi proses lupa kata sandi.', 'RESET_TOKEN_INVALID', 422);
        }

        $user = DB::transaction(function () use ($session, $request) {
            $user = $session->user;
            $user->password = $request->validated('password'); // di-hash oleh cast 'hashed'
            $user->save();
            $user->tokens()->delete(); // paksa logout di semua perangkat
            $session->forceFill(['used_at' => now()])->save();

            return $user;
        });

        return ApiResponse::success($this->authPayload($user), 'Kata sandi berhasil diperbarui.');
    }
}
