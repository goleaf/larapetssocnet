<?php

namespace App\Http\Requests;

use App\Models\Pet;
use App\Services\PersonalityTagService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class StorePetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $personalityTagsMax = (int) config('pets.personality_tags.max', PersonalityTagService::MAX);
        $personalityTagMinLength = (int) config('pets.personality_tags.min_length', PersonalityTagService::MIN_LENGTH);
        $personalityTagMaxLength = (int) config('pets.personality_tags.max_length', PersonalityTagService::MAX_LENGTH);

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
            'personality_tags' => ['nullable', 'array', 'max:'.$personalityTagsMax],
            'personality_tags.*' => ['string', 'min:'.$personalityTagMinLength, 'max:'.$personalityTagMaxLength],
            'is_public' => ['nullable', 'boolean'],
            'is_deceased' => ['nullable', 'boolean'],
            'is_adoptable' => ['nullable', 'boolean'],
            'gallery_photos' => ['nullable', 'array', 'max:30'],
            'gallery_photos.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('personality_tags')) {
            return;
        }

        $rawTags = $this->input('personality_tags');
        $tags = is_string($rawTags) ? explode(',', $rawTags) : Arr::wrap($rawTags);

        $cleaned = collect($tags)
            ->map(static fn (mixed $tag): string => trim((string) $tag))
            ->filter()
            ->values()
            ->all();

        $this->merge([
            'personality_tags' => $cleaned,
        ]);
    }

    protected function passedValidation(): void
    {
        if (! $this->has('personality_tags')) {
            return;
        }

        $normalized = app(PersonalityTagService::class)->normalize($this->input('personality_tags'));

        $this->merge([
            'personality_tags' => $normalized,
        ]);
    }

    public function messages(): array
    {
        return [
            'personality_tags.max' => __('pets.validation.personality_tags_max'),
            'personality_tags.*.min' => __('pets.validation.personality_tag_min'),
            'personality_tags.*.max' => __('pets.validation.personality_tag_max'),
        ];
    }
}
