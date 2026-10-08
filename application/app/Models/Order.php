<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
#[Fillable(["user_id", "event_id", "status", "total_amount"])]
class Order extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            "status" => OrderStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function ticketTypes(): belongsToMany
    {
        return $this->belongsToMany(TicketType::class, 'order_items')
            ->withPivot([
                'quantity',
                'unit_price',
            ])
            ->withTimestamps();
    }
}
