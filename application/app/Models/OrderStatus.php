<?php

namespace App\Models;

enum OrderStatus: string
{
    case PENDING = "pending";
    case PAID = "paid";
    case CANCELLED = "cancelled";
    case EXPIRED = "expired";
}
