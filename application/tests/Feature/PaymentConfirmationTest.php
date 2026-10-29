<?php

namespace Tests\Feature;

use App\Mail\PaymentConfirmation;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(): Order
    {
        $user = User::factory()->create();

        $event = Event::factory()->create();

        return Order::create([
            "user_id" => $user->id,
            "event_id" => $event->id,
            "total_amount" => 500_000,
            "status" => OrderStatus::PAID,
        ]);
    }

    public function testMailableContainsCorrectOrder(): void
    {
        $order = $this->createOrder();

        $mail = new PaymentConfirmation(
            $order
        );

        $this->assertTrue(
            $mail->order->is($order)
        );
    }

    public function testMailableHasCorrectSubject(): void
    {
        $order = $this->createOrder();

        $mail = new PaymentConfirmation(
            $order
        );

        $mail->assertHasSubject(
            "Payment Confirmation"
        );
    }

    public function testMailableContainsOrderInformation(): void
    {
        $order = $this->createOrder();

        $mail = new PaymentConfirmation(
            $order
        );

        $mail->assertSeeInHtml(
            "Your payment for order #{$order->id} was successful."
        );

        $mail->assertSeeInHtml(
            "500000 UZS"
        );

        $mail->assertSeeInHtml(
            "Thank you for your purchase!"
        );
    }
}
