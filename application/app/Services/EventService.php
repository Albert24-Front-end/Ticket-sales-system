<?php

namespace App\Services;

use App\Contracts\AuditLogContract;
use App\Data\Events\EventData;
use App\Models\Event;
use App\Models\EventStatus;
use App\Models\User;
use Illuminate\Validation\ValidationException;

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
            "organizer_id" => $creator->id,
            "description" => $eventData->description,
            "started_at" => $eventData->started_at,
            "ended_at" => $eventData->ended_at,
            "venue_id" => $eventData->venue_id,
            "category_id" => $eventData->category_id,
            "status" => EventStatus::DRAFT,
        ]);
        $this->eventAuditLogService->log(
            action: 'event_created',
            user_id: $creator->id,
            event_id: $event->id,
        );
    }

    public function getUserEvents(User $user)
    {
        return Event::where("organizer_id", $user->id)
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

    public function publishEvent(User $user, Event $event): void
    {
        if ($event->status !== EventStatus::DRAFT) {
            throw ValidationException::withMessages([
                "status" => "Only draft event can be published.",
            ]);
        }

        $event->status = EventStatus::PUBLISHED;
        $event->save();

        $this->eventAuditLogService->log(
            "event_published",
            user_id: $user->id,
            event_id: $event->id,
        );
    }

    public function cancelEvent(User $user, Event $event): void
    {
        if ($event->status !== EventStatus::PUBLISHED) {
            throw ValidationException::withMessages([
                "status" => "Only published event can be cancelled.",
            ]);
        }

        $event->status = EventStatus::CANCELLED;
        $event->save();

        $this->eventAuditLogService->log(
            "event_cancelled",
            user_id: $user->id,
            event_id: $event->id,
        );
    }

    public function deleteEvent(User $user, Event $event)
    {
        $event->delete();
        $this->eventAuditLogService->log("event_deleted", user_id: $user->id, event_id: $event->id);
    }
}
