<?php

namespace App\Data\Order;

class OrderData
{
    public function __construct(
        public array $items,
    )
    {}
}
