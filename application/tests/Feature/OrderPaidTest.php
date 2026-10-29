<?php

namespace Tests\Feature;

use App\Events\OrderPaid;
use App\Listeners\SendPurchaseConfirmation;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderPaidTest extends TestCase
{
    public function testOrderPaidHasPurchaseConfirmationListener(): void
    {
        // проверяем, что слушатель SendPurchaseConfirmation слушает событие OrderPaid, то есть гарантируем, что покупатель получит извещение
        Event::fake();

        Event::assertListening(
            OrderPaid::class,
            SendPurchaseConfirmation::class
        );
    }
}
