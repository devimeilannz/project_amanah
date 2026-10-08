<?php

namespace App\Http\Requests;

class WhatsAppVerifyOtpRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'whatsapp_number' => ['required', self::PHONE_RULE],
            'otp'             => ['required', 'digits:6'],
        ];
    }
}
