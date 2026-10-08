<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'whatsapp_number', 'password', 'terms_accepted_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'whatsapp_verified_at' => 'datetime',
            'locked_until'         => 'datetime',
            'terms_accepted_at'    => 'datetime',
            'last_login_at'        => 'datetime',
            'password'             => 'hashed', // otomatis bcrypt saat di-assign
        ];
    }

    public function otpCodes(): HasMany
    {
        return $this->hasMany(OtpCode::class);
    }

    public function passwordResetSessions(): HasMany
    {
        return $this->hasMany(PasswordResetSession::class);
    }
}
