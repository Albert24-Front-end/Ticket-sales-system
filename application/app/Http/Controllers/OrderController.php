<?php

namespace App\Http\Controllers;

use App\Http\Requests\Order\OrderCreationRequest;
use App\Models\Event;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function index(OrderService $orderService, Event $event)
    {
        return [
            "data" => $orderService->getOrders($event)
        ];
    }

    public function create(OrderCreationRequest $request, Event $event, OrderService $orderService): JsonResponse
    {
        $order = $orderService->createOrder($request->user(), $event, $request->toDTO());

        return response()->json([
            "success" => true,
            "data" => [
                "order_id" => $order->id,
                "status" => $order->status->value,
                "total_amount" => (int) $order->total_amount,
            ],
        ], 201);
    }
}
