<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('mail-relay', function (Request $request): Limit {
            $token = (string) $request->bearerToken();
            $key = $token !== '' ? sha1($token) : (string) $request->ip();

            return Limit::perMinute(60)->by($key);
        });
    }
}
