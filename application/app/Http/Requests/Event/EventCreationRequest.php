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
            "started_at" => ["required", "date"],
            "ended_at" => ["required", "date", "after:started_at"],
            "location" => ["required", "string", "max:255"],
            "organizer" => ["required", "string"],
            "category" => ["required", "string", "max:255"],
            "status" => ["required", "string", "max:255"],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function toDTO(): EventData
    {
        return new EventData(...$this->except('status'),
            status: EventStatus::from($this->input('status', 'draft')));
    }
}
