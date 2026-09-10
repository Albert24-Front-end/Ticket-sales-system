<?php

namespace App\Http\Requests\Venue;

use App\Data\Venues\VenueData;
use Illuminate\Foundation\Http\FormRequest;

class VenueRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            "name" => ["required", "string", "max:255"],
            "address" => ["required", "string"],
            "description" => ["required", "string"],
            "capacity" => ["required", "integer", "min:1"],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function toDTO(): VenueData
    {
        return new VenueData(...$this->validated());
    }
}
