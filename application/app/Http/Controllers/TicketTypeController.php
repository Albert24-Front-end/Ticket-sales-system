<?php

namespace App\Http\Controllers;

use App\Http\Requests\TicketType\TicketTypeRequest;
use App\Models\Event;
use App\Models\TicketType;
use App\Services\TicketTypeService;
use Illuminate\Http\Request;

class TicketTypeController extends Controller
{
    public function index(TicketTypeService $ticketTypeService, Event $event)
    {
        return [
            "data" => $ticketTypeService->getEventTicketTypes($event),
        ];
    }

    public function create(TicketTypeRequest $request, Event $event, TicketTypeService $ticketTypeService)
    {
        $ticketTypeService->createTicketType(auth()->user(), $event, $request->toDTO());
        return response()->json(["success" => true], 201);
    }

    public function update(TicketTypeRequest $request, Event $event, TicketType $ticketType, TicketTypeService $ticketTypeService)
    {
        $ticketTypeService->updateTicketType(auth()->user(), $event, $ticketType, $request->toDTO());
        return ["success" => true];
    }

    public function delete(Event $event, TicketType $ticketType, TicketTypeService $ticketTypeService)
    {
        $ticketTypeService->deleteTicketType(auth()->user(), $event, $ticketType);
        return ["success" => true];
    }
}
