<?php

namespace App\Http\Requests;

class ForgotPasswordRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'channel'         => ['required', 'in:email,whatsapp'],
            'email' => ['required_if:channel,email', 'nullable', 'email', self::EMAIL_RULE],
            'whatsapp_number' => ['required_if:channel,whatsapp', 'nullable', self::PHONE_RULE],
        ];
    }

    public function destination(): string
    {
        return $this->input('channel') === 'email'
            ? (string) $this->input('email')
            : (string) $this->input('whatsapp_number');
    }
}
