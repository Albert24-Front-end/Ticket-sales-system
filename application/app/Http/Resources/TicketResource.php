<?php

namespace App\Http\Resources;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,

            "number" => $this->number,

            "status" => $this->enumValue(
                $this->status
            ),

            "refund_required" => (bool) $this->refund_required,

            "ticket_index" => $this->ticket_index,

            "event_id" => $this->event_id,

            "ticket_type" => $this->whenLoaded(
                "ticketType",
                fn () => [
                    "id" => $this->ticketType->id,
                    "name" => $this->ticketType->name,
                    "price" => $this->ticketType->price,
                ]
            ),

            "order" => $this->whenLoaded(
                "order",
                fn () => [
                    "id" => $this->order->id,

                    "total_amount" =>
                        $this->order->total_amount,

                    "status" => $this->enumValue(
                        $this->order->status
                    ),
                ]
            ),

            "created_at" => $this->created_at?->toISOString(),
        ];
    }

    //  поддерживает как enum cast, ак и обычное строковое значение
    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum
            ? $value->value
            : $value;
    }
}
