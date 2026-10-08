<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $v = (string) $value;

        if (mb_strlen($v) < 8) {
            $fail('Kata sandi minimal 8 karakter.');
        }
        if (! preg_match('/[a-z]/', $v) || ! preg_match('/[A-Z]/', $v)) {
            $fail('Kata sandi harus mengandung huruf besar dan huruf kecil.');
        }
        if (! preg_match('/[0-9]/', $v)) {
            $fail('Kata sandi harus mengandung angka (0-9).');
        }
        if (! preg_match('/[^A-Za-z0-9]/', $v)) {
            $fail('Kata sandi harus mengandung simbol (@!#$).');
        }
    }
}
