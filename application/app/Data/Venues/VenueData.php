<?php

namespace App\Data\Venues;

class VenueData
{
    public function __construct(
        public string $name,
        public string $address,
        public string $description,
        public int $capacity,
    )
    {}
}
