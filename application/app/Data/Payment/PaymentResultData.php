<?php

namespace App\Data\Payment;

class PaymentResultData
{
    public function __construct(
        public string $paymentId,
        public string $paymentUrl,
    )
    {}
}
