<?php

namespace App\Data\TicketTypes;

class TicketTypeData
{
    public function __construct(
        public string $name,
        public string $description,
        public int $price,
        public int $quantity,
    )
    {}
}
