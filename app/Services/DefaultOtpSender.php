<?php

namespace App\Services;

use App\Contracts\OtpSender;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DefaultOtpSender implements OtpSender
{
    public function send(string $channel, string $destination, string $code, string $purpose): void
    {
        $ttl  = config('amanah.otp.ttl_minutes');
        $text = "Kode OTP Amanah Anda: {$code}. Berlaku {$ttl} menit. Jangan bagikan kode ini kepada siapa pun.";

        if ($channel === 'email') {
            Mail::raw($text, fn ($m) => $m->to($destination)->subject('Kode Verifikasi Amanah'));

            return;
        }

        if (config('amanah.whatsapp.driver') === 'http') {
            $res = Http::withHeaders(['Authorization' => (string) config('amanah.whatsapp.token')])
                ->asForm()
                ->timeout(10)
                ->post((string) config('amanah.whatsapp.url'), [
                    'target'  => ltrim($destination, '+'),
                    'message' => $text,
                ]);

            if ($res->failed()) {
                throw new ApiException('Gagal mengirim OTP via WhatsApp. Silakan coba lagi.', 'OTP_DELIVERY_FAILED', 502);
            }

            return;
        }

        // Driver "log": OTP tampil di storage/logs/laravel.log (development).
        Log::info("[OTP][whatsapp][{$purpose}] {$destination}: {$text}");
    }
}
