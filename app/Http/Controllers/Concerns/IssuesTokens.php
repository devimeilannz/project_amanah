<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Resources\UserResource;
use App\Models\User;

trait IssuesTokens
{
    /** Buat token Sanctum + payload standar (user + access_token). */
    protected function authPayload(User $user, bool $remember = false, string $device = 'mobile'): array
    {
        $expires = $remember
            ? now()->addDays((int) config('amanah.login.remember_days'))
            : now()->addHours((int) config('amanah.login.token_ttl_hours'));

        $token = $user->createToken($device, ['*'], $expires);

        $user->forceFill([
            'last_login_at'         => now(),
            'failed_login_attempts' => 0,
            'locked_until'          => null,
        ])->save();

        return [
            'user'         => (new UserResource($user))->resolve(),
            'access_token' => $token->plainTextToken,
            'token_type'   => 'Bearer',
            'expires_at'   => $expires->toIso8601String(),
        ];
    }
}
