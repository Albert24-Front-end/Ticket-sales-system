<?php

namespace App\Contracts;

use App\Data\Payment\PaymentResultData;
use App\Models\Order;
use App\Models\Payment;

interface PaymentGatewayContract
{
    public function createPayment(Order $order): PaymentResultData;
}
