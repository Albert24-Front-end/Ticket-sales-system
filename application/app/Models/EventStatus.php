<?php

namespace App\Models;

enum EventStatus: string
{
    case DRAFT = "draft";
    case PUBLISHED = "published";
    case CANCELLED = "cancelled";
    case FINISHED = "finished";

}
