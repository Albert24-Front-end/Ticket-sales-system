<?php

namespace Tests\Unit;

use App\Events\OrderPaid;
use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use PHPUnit\Framework\TestCase;

class OrderPaidTest extends TestCase
{
    /**
     * A basic unit test example.
     */
    public function testOrderPaidMustBeDispatchedAfterCommit(): void
    {
        // OrderPaid нельзя случайно превратить обратно в обычное событие, которое выполняется внутри незавершённой транзакции
        $event = new OrderPaid(
            new Order()
        );

        $this->assertInstanceOf(
            ShouldDispatchAfterCommit::class,
            $event
        );
    }
}
