<?php

namespace App\Services;

use App\Contracts\OtpSender;
use App\Exceptions\ApiException;
use App\Models\OtpCode;
use App\Models\User;
use App\Support\Mask;

class OtpService
{
    public function __construct(private OtpSender $sender)
    {
    }

    /** Buat & kirim OTP baru. OTP lama yang masih aktif di-invalidate. */
    public function issue(string $purpose, string $channel, string $destination, ?User $user = null, ?string $ip = null): array
    {
        $cooldown = (int) config('amanah.otp.resend_cooldown');
        $scope = ['purpose' => $purpose, 'channel' => $channel, 'destination' => $destination];

        $last = OtpCode::where($scope)->latest('id')->first();
        if ($last && $last->created_at->getTimestamp() + $cooldown > now()->getTimestamp()) {
            $retry = $last->created_at->getTimestamp() + $cooldown - now()->getTimestamp();
            throw new ApiException(
                "Mohon tunggu {$retry} detik sebelum meminta kode baru.",
                'OTP_COOLDOWN',
                429,
                ['retry_after' => $retry],
            );
        }

        OtpCode::where($scope)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $len  = (int) config('amanah.otp.length');
        $code = str_pad((string) random_int(0, (10 ** $len) - 1), $len, '0', STR_PAD_LEFT);

        $otp = OtpCode::create($scope + [
            'user_id'    => $user?->id,
            'code_hash'  => $this->hash($code, $purpose, $destination),
            'expires_at' => now()->addMinutes((int) config('amanah.otp.ttl_minutes')),
            'ip_address' => $ip,
        ]);

        $this->sender->send($channel, $destination, $code, $purpose);

        return [$otp, $code];
    }

    /** Validasi OTP: ada, belum kedaluwarsa, belum melewati batas percobaan, kode cocok, sekali pakai. */
    public function verify(string $purpose, string $channel, string $destination, string $code): OtpCode
    {
        $max = (int) config('amanah.otp.max_attempts');
        $otp = OtpCode::where(['purpose' => $purpose, 'channel' => $channel, 'destination' => $destination])
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $otp) {
            throw new ApiException('Kode OTP tidak valid. Silakan minta kode baru.', 'OTP_INVALID', 422);
        }
        if ($otp->expires_at->isPast()) {
            throw new ApiException('Kode OTP sudah kedaluwarsa. Silakan kirim ulang kode.', 'OTP_EXPIRED', 422);
        }
        if ($otp->attempts >= $max) {
            throw new ApiException('Terlalu banyak percobaan salah. Silakan minta kode baru.', 'OTP_ATTEMPTS_EXCEEDED', 429);
        }

        if (! hash_equals($otp->code_hash, $this->hash($code, $purpose, $destination))) {
            $otp->increment('attempts');
            $remaining = max(0, $max - $otp->attempts);
            throw new ApiException(
                'Kode OTP salah. Sisa percobaan: ' . $remaining . '.',
                'OTP_INVALID',
                422,
                ['remaining_attempts' => $remaining],
            );
        }

        // Atomic: mencegah dua request paralel memakai OTP yang sama.
        $claimed = OtpCode::whereKey($otp->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        if ($claimed === 0) {
            throw new ApiException('Kode OTP tidak valid. Silakan minta kode baru.', 'OTP_INVALID', 422);
        }

        return $otp;
    }

    /** Payload respons setelah OTP dikirim. */
    public function payload(string $channel, string $destination, ?string $code = null): array
    {
        $data = [
            'channel'             => $channel,
            'destination'         => Mask::destination($channel, $destination),
            'expires_in'          => (int) config('amanah.otp.ttl_minutes') * 60,
            'resend_available_in' => (int) config('amanah.otp.resend_cooldown'),
        ];
        if ($code !== null && config('amanah.otp.expose_in_response') && ! app()->isProduction()) {
            $data['debug_otp'] = $code;
        }

        return $data;
    }

    private function hash(string $code, string $purpose, string $destination): string
    {
        return hash_hmac('sha256', "{$code}|{$purpose}|{$destination}", (string) config('app.key'));
    }
}
