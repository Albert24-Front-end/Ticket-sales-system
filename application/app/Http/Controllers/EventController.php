<?php

namespace App\Http\Controllers;

use App\Data\Events\EventData;
use App\Http\Requests\Event\EventCreationRequest;
use App\Models\Event;
use App\Services\EventService;
use Illuminate\Http\Request;
use App\Models\EventStatus;

class EventController extends Controller
{
    public function index(EventService $eventService)
    {
        return [
            "data" => $eventService->getUserEvents(auth()->user())
        ];
    }

    public function create(EventCreationRequest $request, EventService $eventService)
    {
        $eventService->createEvent(
            auth()->user(),
            $request->toDTO(),
        );

        return response()->json(["success" => true], 201);
    }

    public function update(EventCreationRequest $request, Event $event, EventService $eventService)
    {
        $eventService->updateEvent(auth()->user(), $event, $request->toDTO());
        return ["success" => true];
    }

    public function publish(Event $event, EventService $eventService)
    {
        $eventService->publishEvent(auth()->user(), $event);
        return ["success" => true];
    }

    public function cancel(Event $event, EventService $eventService) {
        $eventService->cancelEvent(auth()->user(), $event);

        return ["success" => true];
    }

    public function delete(Event $event, EventService $eventService)
    {
        $eventService->deleteEvent(auth()->user(), $event);
        return ["success" => true];
    }
}
