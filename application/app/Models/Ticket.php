<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use App\Models\TicketStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(["user_id", "event_id", "ticket_type_id", "order_id", "ticket_index", "number", "status", "refund_required"])]
class Ticket extends Model
{
    protected function casts(): array
    {
        return [
            "status" => TicketStatus::class,
            "refund_required" => "boolean",
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
