<?php

namespace App\Services;

use App\Contracts\AuditLogContract;
use App\Data\TicketTypes\TicketTypeData;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class TicketTypeService
{
    public function __construct(
        readonly private AuditLogContract $ticketTypeAuditLogService
    )
    {}

    public function createTicketType(
        User $user,
        Event $event,
        TicketTypeData $ticketTypeData,
    ): TicketType {
        $this->ensureCapacityForCreate($event, $ticketTypeData->quantity);

        $ticketType = $event->ticketTypes()->create([
            "name" => $ticketTypeData->name,
            "description" => $ticketTypeData->description,
            "price" => $ticketTypeData->price,
            "quantity" => $ticketTypeData->quantity,
        ]);

        $this->ticketTypeAuditLogService->log("ticket_type_created", user_id: $user->id, event_id: $event->id);

        return $ticketType;
    }

    public function getEventTicketTypes(Event $event)
    {
        return $event->ticketTypes()
            ->orderByDesc("created_at")
            ->get();
    }

    public function updateTicketType(User $user, Event $event, TicketType $ticketType, TicketTypeData $data): void
    {
        if ($data->quantity < $ticketType->sold_quantity) {
            throw ValidationException::withMessages([
                "quantity" => "Quantity cannot be less than sold quantity.",
            ]);
        }

        $this->ensureCapacityForUpdate(
            $event,
            $ticketType,
            $data->quantity
        );

        $ticketType->fill((array) $data);
        $ticketType->save();
        $this->ticketTypeAuditLogService->log("ticket_type_updated", user_id: $user->id, event_id: $event->id, parameters: (array) $data);
    }

    public function deleteTicketType(User $user, Event $event, TicketType $ticketType): void
    {
        $ticketType->delete();
        $this->ticketTypeAuditLogService->log("ticket_type_deleted", user_id: $user->id, event_id: $event->id,);
    }

    public function ensureCapacityForCreate(Event $event, int $quantity): void
    {
        $currentQuantity = $event->ticketTypes()->sum("quantity");

        if ($currentQuantity + $quantity > $event->venue->capacity) {
            throw ValidationException::withMessages([
                "quantity" => "Total ticket quantity exceeds venue capacity.",
            ]);
        }
    }

    public function ensureCapacityForUpdate(Event $event, TicketType $ticketType, int $quantity): void
    {
        $otherQuantity = $event->ticketTypes()->where("id", "!=", $ticketType->id)->sum("quantity");

        if ($otherQuantity + $quantity > $event->venue->capacity)
        {
            throw ValidationException::withMessages([
                "quantity" => "Total ticket quantity exceeds venue capacity.",
            ]);
        }
    }
}
