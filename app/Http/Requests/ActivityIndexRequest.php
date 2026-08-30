<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class ActivityIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|In>> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'type' => ['nullable', Rule::in(['ride', 'gravel', 'mountain_bike', 'e_bike', 'indoor_ride'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'include_route' => ['nullable', 'boolean'],
            'format' => ['nullable', Rule::in(['json', 'csv'])],
        ];
    }
}
