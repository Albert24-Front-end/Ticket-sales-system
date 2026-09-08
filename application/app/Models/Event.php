<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    "organizer_id",
    "name",
    "description",
    "started_at",
    "ended_at",
    "venue_id",
    "category_id",
    "status"
])]
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

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, "organizer_id");
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

}
