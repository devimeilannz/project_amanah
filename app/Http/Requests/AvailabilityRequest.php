<?php

namespace App\Http\Requests;

class AvailabilityRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required_without:whatsapp_number', 'nullable', 'email', self::EMAIL_RULE],
            'whatsapp_number' => ['required_without:email', 'nullable', self::PHONE_RULE],
        ];
    }
}
