<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\WhatsAppLoginController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function () {
    // Register & login email
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('check-availability', [AuthController::class, 'checkAvailability'])->middleware('throttle:30,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    // Login WhatsApp + OTP
    Route::post('login/whatsapp/request-otp', [WhatsAppLoginController::class, 'requestOtp'])->middleware('throttle:5,1');
    Route::post('login/whatsapp/verify-otp', [WhatsAppLoginController::class, 'verifyOtp'])->middleware('throttle:10,1');

    // Lupa password + OTP
    Route::post('forgot-password', [PasswordResetController::class, 'request'])->middleware('throttle:5,1');
    Route::post('forgot-password/verify-otp', [PasswordResetController::class, 'verifyOtp'])->middleware('throttle:10,1');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:10,1');

    // Terproteksi
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});
