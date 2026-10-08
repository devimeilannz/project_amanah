<?php

namespace App\Http\Requests;

class WhatsAppOtpRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['whatsapp_number' => ['required', self::PHONE_RULE]];
    }
}
