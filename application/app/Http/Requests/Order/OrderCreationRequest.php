<?php

namespace App\Http\Requests\Order;

use App\Data\Order\OrderData;
use Illuminate\Foundation\Http\FormRequest;

class OrderCreationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            "items" => [
                "required",
                "array",
                "min:1",
            ],

            "items.*.ticket_type_id" => [
                "required",
                "integer",
                "distinct",
                "exists:ticket_types,id",
            ],

            "items.*.ticket_quantity" => [
                "required",
                "integer",
                "min:1",
            ],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function toDTO(): OrderData
    {
        return new OrderData(items: $this->validated("items"),);
    }
}
