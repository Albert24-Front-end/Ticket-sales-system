<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\PaymentWebhookRequest;
use App\Models\Order;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function pay(Order $order, PaymentService $paymentService): JsonResponse
    {
        $payment = $paymentService->initiatePayment($order);
        return response()->json([
            "success" => true,
            "data" => [
                "payment_url" => $payment->payment_url,
            ],
        ]);
    }

    public function webhook(PaymentWebhookRequest $request, PaymentService $paymentService): JsonResponse
    {
        $item = $paymentService->processWebhook($request->toDTO());
        return response()->json([
            "success" => true,
            "data" => [
                "order_id" => $item->order_id,
                "amount" => $item->amount,
                "status" => $item->status
            ]
        ]);
    }
}
