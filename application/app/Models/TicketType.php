<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(["name", "description", "price", "quantity"])]
class TicketType extends Model
{
    use SoftDeletes, HasFactory;

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    // получаем вычисляемое свойство available_quantity через метод Laravel/Eloquent accessor - вычисляется каждый раз для модели
    public function getAvailableQuantityAttribute(): int
    {
        return $this->quantity - $this->sold_quantity - $this->reserved_quantity;
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
