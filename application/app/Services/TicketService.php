<?php

namespace App\Services;

use App\Models\TicketStatus;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicketService
{
    private const PER_PAGE = 20;
    // идемпотентно создаем комплект билетов для оплаченного заказа. Этот сервис разгружает listener события OrderPaid - GenerateTickets
    public function generateTickets(Order $order): Collection
    {
        return DB::transaction(function() use ($order) {
            // блокируем Order, чтобы два заказа не генерировали билеты одновременно
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== OrderStatus::PAID) {
                throw ValidationException::withMessages([
                    "order" => [
                        "Tickets can be generated only for a paid order.",
                    ],
                ]);
            }

            $orderItems = $order->orderItems()->orderBy("ticket_type_id")->get();

            if ($orderItems->isEmpty()) {
                throw ValidationException::withMessages([
                    "order" => [
                        "Paid order does not contain any order items.",
                    ],
                ]);
            }

            $tickets = collect();

            foreach ($orderItems as $orderItem) {
                // Если куплено Standard × 3, создаём три самостоятельных Ticket
                for ($ticketIndex = 1; $ticketIndex <= $orderItem->quantity; $ticketIndex++) {
                    // обеспечиваем идемпотентность через firstOrCreate + unique DB constraint
                    $ticket = Ticket::query()->firstOrCreate(
                        [
                            "order_id" => $order->id,
                            "ticket_type_id" =>
                                $orderItem->ticket_type_id,
                            "ticket_index" => $ticketIndex,
                        ],
                        [
                            "user_id" => $order->user_id,
                            "event_id" => $order->event_id,
                            // ULID практически уникален
                            "number" =>
                                "EVT-" . Str::upper(
                                    (string) Str::ulid()
                                ),

                            "status" =>
                                TicketStatus::ACTIVE,

                            "refund_required" => false,
                        ]
                    );
                    $tickets->push($ticket);
                }
            }

            return $tickets;
        });
    }

    public function getUserTickets(User $user): LengthAwarePaginator
    {
        return Ticket::query()
            ->where("user_id", $user->id)
            ->with([
                "ticketType",
                "order",
            ])
            ->latest("id")
            ->paginate(self::PER_PAGE);
    }

    public function getTicketDetails(Ticket $ticket): Ticket
    {
        return $ticket->load([
            "ticketType",
            "order",
        ]);
    }
}
