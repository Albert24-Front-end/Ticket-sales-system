<?php

namespace App\Models;

enum PaymentStatus
{
    case PENDING = "pending";
    case PAID = "paid";
    case FAILED = "failed";
    case EXPIRED = "expired";
}
