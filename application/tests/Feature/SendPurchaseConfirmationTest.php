<?php

namespace Tests\Feature;

use App\Events\OrderPaid;
use App\Listeners\SendPurchaseConfirmation;
use App\Mail\PaymentConfirmation;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendPurchaseConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(): Order
    {
        $user = User::factory()->create([
            "email" => "buyer@example.com",
        ]);

        $event = Event::factory()->create();

        return Order::create([
            "user_id" => $user->id,
            "event_id" => $event->id,
            "total_amount" => 500_000,
            "status" => OrderStatus::PAID,
        ]);
    }

    public function testListenerIsQueued(): void
    {
        // ставим в очередь отправку подтверждения покупки
        $listener = app(
            SendPurchaseConfirmation::class
        );

        $this->assertInstanceOf(
            ShouldQueue::class,
            $listener
        );
    }

    public function testListenerSendsPaymentConfirmationToOrderUser(): void
    {
        Mail::fake();

        $order = $this->createOrder();

        $event = new OrderPaid(
            $order
        );

        $listener = app(
            SendPurchaseConfirmation::class
        );

        // здесь handle вызываем напрямую, тестируем именно работу listener: получил OrderPaid - отправил Mailable.
        $listener->handle($event);

        Mail::assertSent(
            PaymentConfirmation::class,
            function (
                PaymentConfirmation $mail
            ) use ($order) {
                return $mail->hasTo(
                        $order->user->email
                    )
                    && $mail->order->is($order);
            }
        );
    }

    public function testListenerSendsExactlyOneEmail(): void
    {
        Mail::fake();

        $order = $this->createOrder();

        $listener = app(
            SendPurchaseConfirmation::class
        );

        $listener->handle(
            new OrderPaid($order)
        );

        Mail::assertSent(
            PaymentConfirmation::class,
            1
        );
    }
}
