<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(["user_id", "name", "description", "started_at", "ended_at", "location", "organizer", "category", "status"])]
class Event extends Model
{
    use SoftDeletes, HasFactory;

    protected function casts(): array
    {
        return [
            "status" => EventStatus::class,
            "started_at" => "datetime",
            "ended_at" => "datetime",
        ];
    }
}
