<?php

namespace App\Http\Controllers;

use App\Http\Requests\Order\OrderCreationRequest;
use App\Models\Event;
use App\Services\OrderService;

class OrderController extends Controller
{
    public function index(OrderService $orderService, Event $event)
    {
        return [
            "data" => $orderService->getOrders($event)
        ];
    }

    public function create(OrderCreationRequest $request, Event $event, OrderService $orderService)
    {
        $orderService->createOrder(auth()->user(), $event, $request->toDTO());
        return response()->json(["success" => true], 201);
    }
}
