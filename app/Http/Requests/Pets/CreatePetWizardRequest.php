<?php

namespace App\Http\Requests\Pets;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreatePetWizardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Pet::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return self::rulesForValidation();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::messagesForValidation();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(self::normalize($this->all()));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function validateForLivewire(User $actor, array $input): array
    {
        Gate::forUser($actor)->authorize('create', Pet::class);

        /** @var array<string, mixed> $validated */
        $validated = Validator::make(
            self::normalize($input),
            self::rulesForValidation(),
            self::messagesForValidation(),
        )->validate();

        return $validated;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function rulesForValidation(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', 'regex:/\S/'],
            'species' => ['required', 'string', Rule::in(Pet::SPECIES)],
            'species_other' => ['nullable', 'required_if:species,other', 'string', 'max:80'],
            'breed' => ['nullable', 'string', 'max:120'],
            'breed_label' => ['nullable', 'string', 'max:120'],
            'birth_date' => ['required', 'date', 'before:today'],
            'birth_day' => ['required', 'integer', 'between:1,31'],
            'birth_month' => ['required', 'integer', 'between:1,12'],
            'birth_year' => ['required', 'integer', 'between:1900,'.now()->year],
            'sex' => ['required', Rule::in(Pet::GENDERS)],
            'bio' => ['nullable', 'string', 'max:200'],
            'personality_tags' => ['required', 'array', 'min:1', 'max:5'],
            'personality_tags.*' => ['string', Rule::in(self::allowedPersonalityTraits())],
            'spayed_neutered_status' => ['required', Rule::in(Pet::HEALTH_STATUSES)],
            'vaccination_status' => ['required', Rule::in(Pet::HEALTH_STATUSES)],
            'last_vaccinated_on' => ['nullable', 'required_if:vaccination_status,yes', 'date', 'before_or_equal:today'],
            'microchipped_status' => ['required', Rule::in(Pet::HEALTH_STATUSES)],
            'visibility' => ['required', Rule::in(Pet::VISIBILITY)],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=1200,min_height=400'],
            'cover_photo_position' => ['nullable', 'numeric', 'between:0,100'],
            'is_adoptable' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messagesForValidation(): array
    {
        return [
            'name.regex' => 'Pet name cannot be blank.',
            'species.in' => 'Choose a supported species.',
            'species_other.required_if' => 'Tell us the species name for this pet.',
            'birth_date.before' => 'Date of birth must be in the past.',
            'personality_tags.required' => 'Choose at least one personality trait.',
            'personality_tags.min' => 'Choose at least one personality trait.',
            'personality_tags.max' => 'Choose up to 5 personality traits.',
            'personality_tags.*.in' => 'Choose a supported personality trait.',
            'last_vaccinated_on.required_if' => 'Add the last vaccination date or change vaccination status.',
            'avatar.image' => 'Profile photo must be an image file.',
            'avatar.mimes' => 'Profile photo must be a JPG, PNG, or WEBP image.',
            'avatar.max' => 'Profile photo must be 3MB or smaller.',
            'cover.image' => 'Cover photo must be an image file.',
            'cover.mimes' => 'Cover photo must be a JPG, PNG, or WEBP image.',
            'cover.max' => 'Cover photo must be 5MB or smaller.',
            'cover.dimensions' => 'Cover photo must be at least 1200 by 400 pixels.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowedPersonalityTraits(): array
    {
        return [
            'playful',
            'calm',
            'energetic',
            'shy',
            'friendly',
            'independent',
            'cuddly',
            'stubborn',
            'clever',
            'gentle',
            'mischievous',
            'protective',
            'loyal',
            'adventurous',
            'lazy',
            'vocal',
            'quiet',
            'sociable',
            'anxious',
            'fearless',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        foreach (['name', 'species', 'species_other', 'breed', 'breed_label', 'sex', 'bio', 'visibility'] as $field) {
            if (array_key_exists($field, $input)) {
                $input[$field] = self::nullableString($input[$field]);
            }
        }

        $input['name'] = Str::squish((string) ($input['name'] ?? ''));
        $input['species'] = strtolower((string) ($input['species'] ?? 'dog'));
        $input['sex'] = strtolower((string) ($input['sex'] ?? 'unknown'));
        $input['visibility'] = strtolower((string) ($input['visibility'] ?? 'public'));

        foreach (['spayed_neutered_status', 'vaccination_status', 'microchipped_status'] as $statusField) {
            $input[$statusField] = strtolower((string) ($input[$statusField] ?? 'unknown'));
        }

        if (($input['species'] ?? null) !== 'other') {
            $input['species_other'] = null;
        }

        $input['bio'] = isset($input['bio']) ? Str::squish((string) $input['bio']) : null;
        $input['bio'] = $input['bio'] !== '' ? $input['bio'] : null;

        $input['personality_tags'] = collect(Arr::wrap($input['personality_tags'] ?? []))
            ->map(static fn (mixed $tag): string => Str::of((string) $tag)->lower()->replace('_', ' ')->squish()->toString())
            ->filter()
            ->unique()
            ->values()
            ->all();

        $day = (int) ($input['birth_day'] ?? 0);
        $month = (int) ($input['birth_month'] ?? 0);
        $year = (int) ($input['birth_year'] ?? 0);

        if (checkdate($month, $day, $year)) {
            $input['birth_date'] = Carbon::createFromDate($year, $month, $day)->toDateString();
        }

        if (($input['vaccination_status'] ?? null) !== 'yes') {
            $input['last_vaccinated_on'] = null;
        }

        $input['is_adoptable'] = (bool) ($input['is_adoptable'] ?? false);
        $input['cover_photo_position'] = (float) ($input['cover_photo_position'] ?? 50);

        return $input;
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = Str::squish((string) $value);

        return $value !== '' ? $value : null;
    }
}
