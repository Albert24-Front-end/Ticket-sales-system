<?php

namespace App\Data\Payment;

readonly class PaymentResultData
{
    public function __construct(
        public string $paymentUrl,
    )
    {}
}
