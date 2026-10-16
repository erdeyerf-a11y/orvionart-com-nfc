<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        date_default_timezone_set((string) config('app.timezone', 'Europe/Istanbul'));
        RateLimiter::for('admin-login', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email', ''));
            return [
                Limit::perMinute(5)->by('admin-login-ip:'.$request->ip()),
                Limit::perMinute(5)->by('admin-login-email:'.$email.'|'.$request->ip()),
            ];
        });
    }
}
