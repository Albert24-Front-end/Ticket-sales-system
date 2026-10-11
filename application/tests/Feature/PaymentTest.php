<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function testPaymentSuccessfulCreation(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(["user_id" => $user->id, "status" => OrderStatus::PENDING]);


        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
