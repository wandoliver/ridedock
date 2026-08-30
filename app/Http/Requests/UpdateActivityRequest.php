<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'rpe' => ['nullable', 'integer', 'between:1,10'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
