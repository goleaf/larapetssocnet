<?php

namespace App\Http\Requests\Pets;

use App\Models\Pets\Pet;
use App\Services\PersonalityTagService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
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
        $galleryMaxUpload = (int) config('pets.gallery.max_upload', 5);
        $galleryMaxFileSize = (int) config('pets.gallery.max_file_size_kb', 5120);
        $galleryMimes = config('pets.gallery.allowed_mimes', ['jpg', 'jpeg', 'png', 'webp', 'gif']);
        $galleryMimeRule = 'mimes:'.implode(',', (array) $galleryMimes);

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
            'visibility' => ['nullable', 'string', Rule::in(Pet::VISIBILITY)],
            'personality_tags' => ['nullable', 'array', 'max:'.$personalityTagsMax],
            'personality_tags.*' => ['string', 'min:'.$personalityTagMinLength, 'max:'.$personalityTagMaxLength],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'is_public' => ['nullable', 'boolean'],
            'is_deceased' => ['nullable', 'boolean'],
            'is_adoptable' => ['nullable', 'boolean'],
            'gallery_photos' => ['nullable', 'array', 'max:'.$galleryMaxUpload],
            'gallery_photos.*' => ['image', $galleryMimeRule, 'max:'.$galleryMaxFileSize],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = Str::squish((string) $this->input('name'));

        if ($name !== '') {
            $this->merge(['name' => $name]);
        }

        if ($this->has('species')) {
            $species = strtolower(trim((string) $this->input('species')));
            $this->merge(['species' => $species]);
        }

        if ($this->has('breed')) {
            $breed = Str::squish((string) $this->input('breed'));
            $this->merge(['breed' => $breed === '' ? null : $breed]);
        }

        if ($this->has('bio')) {
            $bio = Str::squish((string) $this->input('bio'));
            $this->merge(['bio' => $bio === '' ? null : $bio]);
        }

        if ($this->has('age_text')) {
            $ageText = Str::squish((string) $this->input('age_text'));
            $this->merge(['age_text' => $ageText === '' ? null : $ageText]);
        }

        if ($this->has('sex')) {
            $sex = strtolower(trim((string) $this->input('sex')));
            $this->merge(['sex' => $sex === '' ? null : $sex]);
        }

        if ($this->has('gender')) {
            $gender = strtolower(trim((string) $this->input('gender')));
            $this->merge(['gender' => $gender === '' ? null : $gender]);
        }

        $visibility = $this->input('visibility');
        if ($visibility !== null && ! $this->has('is_public')) {
            $normalized = strtolower(trim((string) $visibility));

            if (in_array($normalized, Pet::VISIBILITY, true)) {
                $this->merge([
                    'is_public' => $normalized === 'public',
                ]);
            }
        }

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
            'gallery_photos.max' => __('pets.validation.gallery_upload_max'),
            'gallery_photos.*.image' => __('pets.validation.gallery_image'),
            'gallery_photos.*.mimes' => __('pets.validation.gallery_image'),
            'gallery_photos.*.max' => __('pets.validation.gallery_file_size'),
        ];
    }
}
