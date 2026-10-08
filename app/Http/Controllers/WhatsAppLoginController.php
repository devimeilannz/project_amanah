<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Controllers\Concerns\IssuesTokens;
use App\Http\Requests\WhatsAppOtpRequest;
use App\Http\Requests\WhatsAppVerifyOtpRequest;
use App\Models\User;
use App\Services\OtpService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class WhatsAppLoginController extends Controller
{
    use IssuesTokens;

    public function __construct(private OtpService $otp)
    {
    }

    public function requestOtp(WhatsAppOtpRequest $request): JsonResponse
    {
        $number = $request->validated('whatsapp_number');
        $user   = User::where('whatsapp_number', $number)->first();

        if (! $user) {
            throw new ApiException('Nomor WhatsApp belum terdaftar. Silakan daftar terlebih dahulu.', 'USER_NOT_FOUND', 404);
        }

        [, $code] = $this->otp->issue('login', 'whatsapp', $number, $user, $request->ip());

        return ApiResponse::success(
            $this->otp->payload('whatsapp', $number, $code),
            'Kode OTP telah dikirim ke WhatsApp Anda.'
        );
    }

    public function verifyOtp(WhatsAppVerifyOtpRequest $request): JsonResponse
    {
        $number = $request->validated('whatsapp_number');
        $this->otp->verify('login', 'whatsapp', $number, $request->validated('otp'));

        $user = User::where('whatsapp_number', $number)->first();
        if (! $user) {
            throw new ApiException('Nomor WhatsApp belum terdaftar.', 'USER_NOT_FOUND', 404);
        }

        if (! $user->whatsapp_verified_at) {
            $user->forceFill(['whatsapp_verified_at' => now()])->save();
        }

        return ApiResponse::success($this->authPayload($user), 'Login berhasil.');
    }
}
