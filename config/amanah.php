<?php

return [
    'otp' => [
        'length'             => 6,
        'ttl_minutes'        => (int) env('OTP_TTL_MINUTES', 5),
        'max_attempts'       => (int) env('OTP_MAX_ATTEMPTS', 5),
        'resend_cooldown'    => (int) env('OTP_RESEND_COOLDOWN', 60),
        // Hanya untuk development/testing. Otomatis diabaikan di production.
        'expose_in_response' => (bool) env('OTP_EXPOSE_IN_RESPONSE', false),
    ],

    'reset_token_ttl_minutes' => (int) env('RESET_TOKEN_TTL_MINUTES', 10),

    'login' => [
        'max_attempts'   => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'lock_minutes'   => (int) env('LOGIN_LOCK_MINUTES', 15),
        'token_ttl_hours' => 24,
        'remember_days'  => 30,
    ],

    // driver: log (default, OTP ditulis ke storage/logs) | http (gateway WA, mis. Fonnte)
    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'log'),
        'url'    => env('WHATSAPP_API_URL'),
        'token'  => env('WHATSAPP_API_TOKEN'),
    ],
];
