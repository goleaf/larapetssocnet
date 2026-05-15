<?php

namespace App\Http\Requests\Pets;

use App\Models\Pets\Pet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePetHealthLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pet = $this->route('pet');

        if (! $pet instanceof Pet) {
            return false;
        }

        return $this->user()?->can('update', $pet) ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['weight', 'medication', 'vaccination', 'vaccine', 'vet_visit'])],
            'title' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'logged_at' => ['required', 'date'],
            'next_due_at' => ['nullable', 'date', 'after_or_equal:logged_at', 'prohibits:next_due_in,next_due_unit,next_due_interval'],
            'next_due_in' => ['nullable', 'numeric', 'min:1', 'prohibits:next_due_at,next_due_interval'],
            'next_due_unit' => ['nullable', 'string', 'required_with:next_due_in', Rule::in([
                'minutes',
                'hours',
                'days',
                'weeks',
                'months',
                'years',
            ]), 'prohibits:next_due_at,next_due_interval'],
            'next_due_interval' => ['nullable', 'string', 'prohibits:next_due_at,next_due_in,next_due_unit'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Type must be one of: weight, medication, vaccination, vet visit.',
        ];
    }
}
