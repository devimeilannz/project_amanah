<?php

namespace App\Http\Requests;

class VerifyResetOtpRequest extends ForgotPasswordRequest
{
    public function rules(): array
    {
        return parent::rules() + ['otp' => ['required', 'digits:6']];
    }
}
