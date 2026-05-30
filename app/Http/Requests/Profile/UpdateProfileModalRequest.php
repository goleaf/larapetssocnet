<?php

namespace App\Http\Requests\Profile;

use App\Models\Identity\User;
use App\Support\Profiles\SocialLinkNormalizer;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UpdateProfileModalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $target = $this->targetUser();

        return $actor instanceof User
            && $target instanceof User
            && $actor->can('updateProfile', $target);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return self::rulesFor($this->targetUser());
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
    public static function validateInput(User $target, User $actor, array $input): array
    {
        Gate::forUser($actor)->authorize('updateProfile', $target);

        /** @var array<string, mixed> $validated */
        $validated = Validator::make(
            self::normalize($input),
            self::rulesFor($target),
            self::messagesForValidation(),
        )->validate();

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function validateForLivewire(User $target, User $actor, array $input): array
    {
        return self::validateInput($target, $actor, $input);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function rulesFor(?User $user): array
    {
        $userId = $user?->getKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => UsernameRules::requiredRules(is_numeric($userId) ? (int) $userId : null),
            'display_name' => ['nullable', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:160'],
            'headline' => ['nullable', 'string', 'max:120'],
            'pronouns' => ['nullable', 'string', 'max:32'],
            'location' => ['nullable', 'string', 'max:255'],
            'location_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'location_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'website' => ['nullable', 'url', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'in:male,female,other,prefer_not_to_say'],
            'social_links' => ['nullable', 'array', 'max:4'],
            'social_links.x' => ['nullable', 'string', 'max:16', 'regex:/^@[A-Za-z0-9_]{1,15}$/'],
            'social_links.instagram' => ['nullable', 'string', 'max:31', 'regex:/^@[A-Za-z0-9](?:[A-Za-z0-9._]{0,28}[A-Za-z0-9])?$/'],
            'social_links.facebook' => ['nullable', 'url:http,https', 'max:255'],
            'social_links.youtube' => ['nullable', 'url:http,https', 'max:255'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120', 'dimensions:min_width=1200,min_height=400'],
            'cover_photo_position' => ['nullable', 'numeric', 'between:0,100'],
            'remove_avatar' => ['boolean'],
            'remove_cover' => ['boolean'],
            'bio_html' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messagesForValidation(): array
    {
        return [
            'username.min' => 'Username must be '.UsernameRules::minLength().'-'.UsernameRules::maxLength().' characters.',
            'username.max' => 'Username must be '.UsernameRules::minLength().'-'.UsernameRules::maxLength().' characters.',
            'username.regex' => 'Only letters, numbers, hyphens, and underscores allowed.',
            'username.unique' => 'Username is already taken.',
            'display_name.max' => 'Display name must be 50 characters or fewer.',
            'bio.max' => 'Bio must be 160 characters or fewer.',
            'website.url' => 'Enter a valid website URL.',
            'location_lat.between' => 'Select a valid location suggestion.',
            'location_lng.between' => 'Select a valid location suggestion.',
            'birth_date.date' => 'Enter a valid date of birth.',
            'birth_date.before' => 'Date of birth must be before today.',
            'avatar.image' => 'Avatar must be an image file.',
            'avatar.mimes' => 'Avatar must be a JPG, PNG, or WEBP image.',
            'avatar.max' => 'Avatar must be smaller than 3MB.',
            'cover.image' => 'Cover must be an image file.',
            'cover.mimes' => 'Cover must be a JPG, PNG, WEBP, or GIF image.',
            'cover.max' => 'Cover must be smaller than 5MB.',
            'cover.dimensions' => 'Cover photo must be at least 1200 by 400 pixels.',
            'cover_photo_position.between' => 'Choose a valid cover crop position.',
            'social_links.x.regex' => 'Enter a valid Twitter/X username.',
            'social_links.instagram.regex' => 'Enter a valid Instagram username.',
            'social_links.facebook.url' => 'Enter a valid Facebook profile URL.',
            'social_links.youtube.url' => 'Enter a valid YouTube channel URL.',
            'bio_html.prohibited' => 'Bio HTML is generated automatically and cannot be submitted directly.',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        foreach ([
            'name',
            'display_name',
            'bio',
            'headline',
            'pronouns',
            'location',
            'location_lat',
            'location_lng',
            'birth_date',
            'gender',
        ] as $field) {
            if (array_key_exists($field, $input)) {
                $input[$field] = self::nullableString($input[$field]);
            }
        }

        $input['name'] = trim((string) ($input['name'] ?? ''));
        $input['username'] = UsernameNormalizer::normalize((string) ($input['username'] ?? ''));

        $website = trim((string) ($input['website'] ?? ''));
        if ($website !== '' && ! preg_match('/^https?:\\/\\//i', $website)) {
            $website = 'https://'.$website;
        }

        $input['website'] = $website !== '' ? $website : null;

        if (($input['location'] ?? null) === null) {
            $input['location_lat'] = null;
            $input['location_lng'] = null;
        }

        $normalizedSocialLinks = SocialLinkNormalizer::normalizeInputs($input['social_links'] ?? []);
        $input['social_links'] = $normalizedSocialLinks !== [] ? $normalizedSocialLinks : null;

        foreach (['remove_avatar', 'remove_cover'] as $field) {
            $input[$field] = (bool) ($input[$field] ?? false);
        }

        return $input;
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function targetUser(): ?User
    {
        $target = $this->route('user');

        if ($target instanceof User) {
            return $target;
        }

        $actor = $this->user();

        return $actor instanceof User ? $actor : null;
    }
}
