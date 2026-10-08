<?php

namespace App\Http\Requests;

class ResetPasswordRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'reset_token' => ['required', 'string'],
            'password'    => $this->passwordRules(),
        ];
    }
}
