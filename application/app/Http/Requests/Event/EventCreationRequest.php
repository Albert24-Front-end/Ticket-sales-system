<?php

namespace App\Http\Requests\Event;

use App\Data\Events\EventData;
use App\Models\EventStatus;
use Illuminate\Foundation\Http\FormRequest;

class EventCreationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            "name" => ["required", "string", "max:255"],
            "description" => ["required", "string"],
            "started_at" => ["required", "date", "after:now",],
            "ended_at" => ["required", "date", "after:started_at"],
            "category_id" => [
                "required",
                "integer",
                "exists:categories,id",
            ],
            "venue_id" => [
                "required",
                "integer",
                "exists:venues,id",
            ],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function toDTO(): EventData
    {
        return new EventData(...$this->validated());
    }
}
