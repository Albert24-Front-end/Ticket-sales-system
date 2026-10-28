<?php

namespace App\Providers;

use App\Contracts\AuditLogContract;
use App\Contracts\SignatureContract;
use App\Services\OwnDBAuditLogService;
use App\Services\SignatureService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AuditLogContract::class, function ($app) {
            return new OwnDBAuditLogService();
        });
        $this->app->singleton(SignatureContract::class, function ($app) {
            return new SignatureService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for("reg", function (Request $request) {
            return Limit::perMinutes(30, 10)->by($request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinutes(30, 3)
                ->by($request->email ?: $request->ip())
                ->after(function (Response $response) {
                    return $response->status() === 422;
                });
        });
    }
}
