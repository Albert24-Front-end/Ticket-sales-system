<?php

namespace App\Services;

use App\Contracts\AuditLogContract;
use App\Data\Events\EventData;
use App\Models\Event;
use App\Models\User;

class EventService
{
    public function __construct(
        readonly private AuditLogContract $eventAuditLogService
    )
    {}

    public function createEvent(User $creator, EventData $eventData)
    {
        $event = Event::create([
            "name" => $eventData->name,
            "user_id" => $creator->id,
            "description" => $eventData->description,
            "started_at" => $eventData->started_at,
            "ended_at" => $eventData->ended_at,
            "location" => $eventData->location,
            "organizer" => $eventData->organizer,
            "category" => $eventData->category,
            "status" => $eventData->status,
        ]);
        $this->eventAuditLogService->log(
            action: 'event_created',
            user_id: $creator->id,
            event_id: $event->id,
        );
    }

    public function getUserEvents(User $user)
    {
        return Event::where("user_id", $user->id)
            ->orderByDesc("created_at")
            ->get();
//            ->toResourceCollection();
    }

    public function updateEvent(User $user, Event $event, EventData $eventData)
    {
        $event->fill((array) $eventData);
        $event->save();
        $this->eventAuditLogService->log("event_updated", user_id: $user->id, event_id: $event->id, parameters: (array) $eventData);
    }

    public function deleteEvent(User $user, Event $event)
    {
        $event->delete();
        $this->eventAuditLogService->log("event_deleted", user_id: $user->id, event_id: $event->id);
    }
}
