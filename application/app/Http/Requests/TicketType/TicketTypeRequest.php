<?php

namespace App\Http\Requests\TicketType;

use App\Data\TicketTypes\TicketTypeData;
use Illuminate\Foundation\Http\FormRequest;

class TicketTypeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            "name" => [
                "required",
                "string",
                "max:255",
            ],

            "description" => [
                "required",
                "string",
            ],

            "price" => [
                "required",
                "integer",
                "min:0",
            ],

            "quantity" => [
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

    public function toDTO(): TicketTypeData
    {
        return new TicketTypeData(
            ...$this->validated()
        );
    }
}
