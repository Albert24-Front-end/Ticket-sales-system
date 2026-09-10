<?php

namespace App\Http\Requests\Category;

use App\Data\Categories\CategoryData;
use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            "name" => ["required", "string", "max:255"],
            "description" => ["required", "string"],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function toDTO(): CategoryData
    {
        return new CategoryData(...$this->validated());
    }
}
