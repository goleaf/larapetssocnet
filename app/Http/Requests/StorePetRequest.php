<?php

namespace App\Http\Requests;

use App\Models\Pet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'species' => ['required', 'string', Rule::in(Pet::SPECIES)],
            'breed' => ['nullable', 'string', 'max:120'],
            'sex' => ['nullable', Rule::in(Pet::GENDERS)],
            'gender' => ['nullable', Rule::in(Pet::GENDERS)],
            'size' => ['nullable', Rule::in(Pet::SIZES)],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'age_text' => ['nullable', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:500'],
            'personality_tags' => ['nullable', 'string', 'max:500'],
            'is_public' => ['nullable', 'boolean'],
            'is_deceased' => ['nullable', 'boolean'],
            'is_adoptable' => ['nullable', 'boolean'],
            'gallery_photos' => ['nullable', 'array', 'max:30'],
            'gallery_photos.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ];
    }
}
