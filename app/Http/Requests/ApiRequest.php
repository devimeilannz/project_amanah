<?php

namespace App\Http\Requests;

use App\Rules\StrongPassword;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

abstract class ApiRequest extends FormRequest
{
    protected const PHONE_RULE = 'regex:/^\+628[0-9]{8,11}$/';
    protected const EMAIL_RULE = 'regex:/^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$/';
    
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if (is_string($this->input('email'))) {
            $merge['email'] = Str::lower(trim($this->input('email')));
        }
        $wa = $this->input('whatsapp_number');
        if (is_string($wa) && $wa !== '') {
            $merge['whatsapp_number'] = Phone::normalize($wa);
        }
        if ($merge) {
            $this->merge($merge);
        }
    }

    protected function passwordRules(): array
    {
        return ['required', 'string', 'confirmed', new StrongPassword()];
    }

    public function messages(): array
    {
        return [
            'required'             => ':attribute wajib diisi.',
            'required_if'          => ':attribute wajib diisi.',
            'required_without'     => 'Email atau nomor WhatsApp wajib diisi salah satu.',
            'email'                => 'Format :attribute tidak valid.',
            'max'                  => ':attribute maksimal :max karakter.',
            'confirmed'            => 'Konfirmasi :attribute tidak cocok.',
            'accepted'             => 'Anda harus menyetujui Syarat & Ketentuan serta Kebijakan Privasi.',
            'in'                   => ':attribute tidak valid.',
            'digits'               => ':attribute harus berupa :digits digit angka.',
            'string'               => ':attribute harus berupa teks.',
            'boolean'              => ':attribute harus bernilai true atau false.',
            'regex'                => 'Format :attribute tidak valid ',
            'email.regex'          => 'Format Email tidak valid (contoh: nama@domain.com).',
            'whatsapp_number.regex' => 'Format Nomor WhatsApp tidak valid (contoh: +62 812-3456-7890).',
            'unique'               => ':attribute sudah terdaftar.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'            => 'Nama lengkap',
            'email'           => 'Email',
            'whatsapp_number' => 'Nomor WhatsApp',
            'password'        => 'Kata sandi',
            'otp'             => 'Kode OTP',
            'channel'         => 'Metode verifikasi',
            'reset_token'     => 'Token reset',
            'terms_accepted'  => 'Persetujuan',
        ];
    }
}
