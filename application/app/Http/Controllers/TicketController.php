<?php

namespace App\Http\Controllers;

use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    public function index(
        Request $request,
        TicketService $ticketService
    ): AnonymousResourceCollection {
        Gate::authorize(
            "viewAny",
            Ticket::class
        );

        $tickets = $ticketService->getUserTickets(
            $request->user()
        );

        return TicketResource::collection(
            $tickets
        );
    }

    public function show(
        Ticket $ticket,
        TicketService $ticketService
    ): TicketResource {
        Gate::authorize(
            "view",
            $ticket
        );

        $ticket = $ticketService->getTicketDetails(
            $ticket
        );

        return new TicketResource(
            $ticket
        );
    }
}
