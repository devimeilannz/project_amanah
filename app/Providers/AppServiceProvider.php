<?php

namespace App\Providers;

use App\Contracts\OtpSender;
use App\Services\DefaultOtpSender;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OtpSender::class, DefaultOtpSender::class);
    }

    public function boot(): void
    {
        //
    }
}
