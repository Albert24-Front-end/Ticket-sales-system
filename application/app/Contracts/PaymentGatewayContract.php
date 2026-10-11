<?php

namespace App\Contracts;

use App\Data\Payment\PaymentResultData;
use App\Models\Order;

interface PaymentGatewayContract
{
    public function createPayment(Order $order): PaymentResultData;
}
