<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('cache-api', function ($request) {
            return Limit::perMinute(60)->by($request->ip());
        });
        RateLimiter::for('pdf-api', function ($request) {
            return Limit::perMinute(20)->by($request->ip());
        });
    }
}
