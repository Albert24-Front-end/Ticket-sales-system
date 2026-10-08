<?php

namespace App\Data\Order;

class OrderItemData
{
    public function __construct(
        public int $ticketQuantity,
        public int $ticketTypeId,
    )
    {}
}
