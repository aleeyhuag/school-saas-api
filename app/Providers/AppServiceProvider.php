<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        /*
         * Laravel's API middleware is enabled in bootstrap/app.php via
         * $middleware->throttleApi(), which applies the named "api"
         * limiter to the whole API middleware group.
         *
         * Without this registration Laravel throws:
         * "Rate limiter [api] is not defined."
         *
         * We deliberately keep this as a platform-wide safety net.
         * Sensitive public endpoints such as login, password reset and
         * school registration have their own tighter route-level limits
         * in routes/api-auth.php.
         *
         * The key uses the authenticated user ID when authentication has
         * already happened; otherwise it falls back to the client IP.
         * In practice, the API middleware can run before auth:sanctum, so
         * unauthenticated requests will normally be IP-keyed.
         */
        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(60)->by((string) $key);
        });
    }
}
