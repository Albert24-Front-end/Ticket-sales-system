<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPaid implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;
    // Dispatchable позволяет писать: OrderPaid::dispatch($order);
    // SerializesModels корректно работает с Eloquent-моделью, если позже listener будет queued.
    // ShouldDispatchAfterCommit - событие будет фактически передано listeners только после успешного commit; при rollback оно будет отброшено
    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly Order $order,
    )
    {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
