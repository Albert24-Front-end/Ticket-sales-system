<?php

namespace App\Models;

enum PaymentStatus: string
{
    case PENDING = "pending";
    case PAID = "paid";
    case FAILED = "failed";
}
