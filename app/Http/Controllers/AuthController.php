<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Controllers\Concerns\IssuesTokens;
use App\Http\Requests\AvailabilityRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    use IssuesTokens;

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name'              => $request->validated('name'),
            'email'             => $request->validated('email'),
            'whatsapp_number'   => $request->validated('whatsapp_number'),
            'password'          => $request->validated('password'), // di-hash oleh cast 'hashed'
            'terms_accepted_at' => now(),
        ]);

        return ApiResponse::success($this->authPayload($user), 'Registrasi berhasil.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $max  = (int) config('amanah.login.max_attempts');
        $user = User::where('email', $request->validated('email'))->first();

        if ($user && $user->locked_until && $user->locked_until->isFuture()) {
            $retry = $user->locked_until->getTimestamp() - now()->getTimestamp();
            throw new ApiException(
                'Akun dikunci sementara karena terlalu banyak percobaan gagal. Coba lagi nanti atau reset kata sandi.',
                'ACCOUNT_LOCKED',
                423,
                ['retry_after' => $retry],
            );
        }

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            $remaining = null;
            if ($user) {
                $attempts = $user->failed_login_attempts + 1;
                $update   = ['failed_login_attempts' => $attempts];
                $remaining = max(0, $max - $attempts);
                if ($attempts >= $max) {
                    $update = [
                        'failed_login_attempts' => 0,
                        'locked_until'          => now()->addMinutes((int) config('amanah.login.lock_minutes')),
                    ];
                }
                $user->forceFill($update)->save();
            }

            throw new ApiException(
                'Kombinasi email atau kata sandi tidak cocok. Silakan periksa kembali atau reset sandi Anda.',
                'AUTH_FAILED',
                401,
                ['remaining_attempts' => $remaining],
            );
        }

        return ApiResponse::success(
            $this->authPayload($user, (bool) $request->validated('remember', false)),
            'Login berhasil.'
        );
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(['user' => (new UserResource($request->user()))->resolve()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return ApiResponse::success(null, 'Logout berhasil.');
    }

    /** Cek real-time "Sudah Terdaftar" pada form register. */
    public function checkAvailability(AvailabilityRequest $request): JsonResponse
    {
        $data = [];
        if ($request->filled('email')) {
            $data['email'] = ['available' => ! User::where('email', $request->input('email'))->exists()];
        }
        if ($request->filled('whatsapp_number')) {
            $data['whatsapp_number'] = ['available' => ! User::where('whatsapp_number', $request->input('whatsapp_number'))->exists()];
        }

        return ApiResponse::success($data);
    }
}
