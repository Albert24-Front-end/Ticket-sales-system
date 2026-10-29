<?php

namespace App\Data\Payment;

readonly class PaymentWebhookData
{
    public function __construct(
        public string $order_id,
        public int $amount, // в тийинах - из PS
        public int $status,
    )
    {}
}
