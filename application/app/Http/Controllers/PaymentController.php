<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\PaymentWebhookRequest;
use App\Models\Order;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

    public function handleWebhook(PaymentWebhookRequest $request, PaymentService $paymentService): Response
    {
        // выдаем ответ 204 No content
        $signature = $request->header("X-Signature");
        if (!is_string($signature)) {
            throw new HttpException(401, "X-Signature header is missing");
        }
        $paymentService->processWebhook($request->toDTO(), $signature);
        return response()->noContent();
    }
}
