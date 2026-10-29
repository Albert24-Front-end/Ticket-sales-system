<?php

namespace App\Models;

enum TicketStatus: string
{
    case ACTIVE = "active";
    case USED = "used";
    case CANCELLED = "cancelled";
}
