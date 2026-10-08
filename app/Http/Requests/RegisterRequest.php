<?php

namespace App\Http\Requests;

class RegisterRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:191', self::EMAIL_RULE, 'unique:users,email'],
            'whatsapp_number' => ['required', self::PHONE_RULE, 'unique:users,whatsapp_number'],
            'password'        => $this->passwordRules(),
            'terms_accepted'  => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'email.unique'           => 'Email ini sudah terdaftar. Silakan gunakan email lain atau masuk.',
            'whatsapp_number.unique' => 'Nomor WhatsApp ini sudah terdaftar di akun aktif.',
        ];
    }
}
