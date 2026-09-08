<?php

namespace App\Data\Events;

use App\Models\EventStatus;

class EventData
{
    public function __construct(
        public string      $name,
        public string      $description,
        public string      $started_at,
        public string      $ended_at,
        public int         $venue_id,
        public int         $category_id,
    )
    {}
}
