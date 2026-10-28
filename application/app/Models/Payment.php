<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(["order_id", "cashbox_id", "status", "amount", "payment_url"])]
class Payment extends Model
{
//    Payment — локальная запись: какой внешний платёж относится к какому Order и в каком состоянии его видим.
    use HasFactory;
    protected function casts(): array
    {
        return [
            "status" => PaymentStatus::class
        ];
    }
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
