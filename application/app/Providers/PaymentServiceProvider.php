<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayContract;
use App\Services\Payments\EventPaymentGateway;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    // регистрация зависимостей платёжного модуля - этот провайдер зареган в bootstrap/providers.php (с Laravel 11+)
    public function register(): void
    {
        $this->app->singleton(
            PaymentGatewayContract::class,
            EventPaymentGateway::class,
        );
    }

    public function boot(): void
    {

    }
}
