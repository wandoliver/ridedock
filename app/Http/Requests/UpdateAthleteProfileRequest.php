<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAthleteProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', Rule::in(['de', 'en'])],
            'goal' => ['nullable', 'string', 'max:500'],
            'goal_date' => ['nullable', 'date'],
            'experience_level' => ['nullable', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'weekly_hours' => ['nullable', 'numeric', 'between:1,40'],
            'available_days' => ['array', 'max:7'],
            'available_days.*' => [
                'string',
                'distinct',
                Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
            ],
            'ftp_watts' => ['nullable', 'integer', 'between:50,600'],
            'max_heart_rate' => ['nullable', 'integer', 'between:100,230'],
            'constraints' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('locale')) {
            $this->merge(['locale' => $this->user()->locale]);
        }

        if (! $this->has('available_days')) {
            $this->merge(['available_days' => []]);
        }
    }
}
