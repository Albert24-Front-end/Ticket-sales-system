<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Services\TicketService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class GenerateTickets implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 5;
    /**
     * Create the event listener.
     */
    public function __construct(
        private readonly TicketService $ticketService,
    )
    {
        //
    }

    /**
     * Handle the event.
     */
    // если генерация билетов временно упадёт, queue worker сможет повторить её
    public function handle(OrderPaid $event): void
    {
        $this->ticketService->generateTickets($event->order);
    }
}
