<?php

namespace App\Services;

use App\Contracts\AuditLogContract;
use App\Data\Order\OrderData;
use App\Models\Event;
use App\Models\EventStatus;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        readonly private AuditLogContract $orderAuditLogService
    )
    {
    }

//проверка Event
//проверка TicketType
//проверка количества
//расчёт суммы
//создание Order pending
//создание OrderItems
//резервирование

    public function createOrder(User $user, Event $event, OrderData $orderData): Order
    {
        return DB::transaction(function () use ($user, $event, $orderData) {
            if ($event->status !== EventStatus::PUBLISHED) {
                throw ValidationException::withMessages(
                    ["event" => "Tickets can only be purchased for a published event."]
                );
            }

            $totalAmount = 0;
            $preparedItems = [];
            // проверяем все позиции заказа
            foreach ($orderData->items as $index => $item) {
                // ищем ticket type среди типов текущего event
                $ticketType = TicketType::query()
                    ->where("id", $item["ticket_type_id"])
                    ->where("event_id", $event->id)
                    ->lockForUpdate()
                    ->first();
                // проверка, если тип билет относится к другому событию
                if ($ticketType === null) {
                    throw ValidationException::withMessages(
                        ["items.{$index}.ticket_type_id" => "Ticket type not found."]
                    );
                }

                $requestedQuantity = $item["ticket_quantity"];

                if ($requestedQuantity > $ticketType->available_quantity) {
                    throw ValidationException::withMessages(
                        ["items.{$index}.ticket_quantity" => "Requested ticket quantity exceeds available quantity."]
                    );
                }

                $unitPrice = $ticketType->price;
                $totalAmount += $unitPrice * $requestedQuantity;

                $preparedItems[] = [
                    "ticket_type" => $ticketType,
                    "quantity" => $requestedQuantity,
                    "unit_price" => $unitPrice,
                ];
            }

            $order = Order::create([
                "user_id" => $user->id,
                "event_id" => $event->id,
                "total_amount" => $totalAmount,
                "status" => OrderStatus::PENDING,
            ]);

            // создаем order_item и резервируем билеты
            foreach ($preparedItems as $item) {
                /** @var TicketType $ticketType */
                $ticketType = $item["ticket_type"];

                $order->orderItems()->create([
                    // order_id = $order->id подставит сам Eloquent через relationship $order->orderItems()->create(...)
                    "ticket_type_id" => $ticketType->id,
                    "quantity" => $item["quantity"],
                    "unit_price" => $item["unit_price"],
                ]);

                $ticketType->increment("reserved_quantity", $item["quantity"]);
            }

            $this->orderAuditLogService->log("order_created", user_id: $user->id, event_id: $event->id);

            return $order;
        });
    }

    public function getOrders(Event $event)
    {
        return $event->orders()
            ->with("orderItems.ticketType")
            ->orderByDesc("created_at")
            ->get();
    }

    private function confirmReservation(Order $order): void
    {
        $orderItems = $order->orderItems->orderBy("ticket_type_id")->get();

        foreach($orderItems as $orderItem) {
            $ticketType = TicketType::query()
                ->whereKey($orderItem->ticket_type_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $ticketType->reserved_quantity
                < $orderItem->quantity
            ) {
                throw ValidationException::withMessages([
                    "order" =>
                        "Reserved ticket quantity is inconsistent.",
                ]);
            }

            $ticketType->decrement(
                "reserved_quantity",
                $orderItem->quantity
            );
            $ticketType->increment(
                "sold_quantity",
                $orderItem->quantity
            );
        }
    }

    public function markOrderAsPaid(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            //  обеспечиваем идемпотентность: повторный success webhook ничего во второй раз не продаёт
            if ($order->status === OrderStatus::PAID) {
                return;
            }

            // обеспечиваем единственный нормальный переход
            if ($order->status !== OrderStatus::PENDING) {
                throw ValidationException::withMessages([
                    "order" => "Order is not pending.",
                ]);
            }

            $this->confirmReservation($order);
            $order->update([
                "status" => OrderStatus::PAID,
            ]);
        });
    }

    private function releaseReservation(Order $order): void
    {
        $orderItems = $order->orderItems->orderBy("ticket_type_id")->get();
        foreach ($orderItems as $orderItem) {
            $ticketType = TicketType::query()
                ->whereKey($orderItem->ticket_type_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $ticketType->reserved_quantity
                < $orderItem->quantity
            ) {
                throw ValidationException::withMessages([
                    "order" =>
                        "Reserved ticket quantity is inconsistent.",
                ]);
            }

            $ticketType->decrement(
                "reserved_quantity",
                $orderItem->quantity
            );
        }
    }

    public function cancelOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();
            // повторный failed webhook
            if ($order->status === OrderStatus::CANCELLED) {
                return;
            }

            // оплаченный заказ отменять таким webhook уже нельзя
            if (
                $order->status !== OrderStatus::PENDING
            ) {
                throw ValidationException::withMessages([
                    "order" =>
                        "Only a pending order can be cancelled.",
                ]);
            }
            $this->releaseReservation($order);
            $order->update([
                "status" => OrderStatus::CANCELLED,
            ]);

        });
    }
}
