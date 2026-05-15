<?php

namespace App\Http\Requests\Profile;

use App\Models\Identity\User;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor) {
            return false;
        }

        $target = $this->route('user');

        if ($target instanceof User) {
            return $actor->can('update', $target);
        }

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $userId = $this->user()?->getAuthIdentifier();

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:120'],
            'username' => UsernameRules::requiredRules(is_numeric($userId) ? (int) $userId : null),
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'bio' => ['nullable', 'string', 'max:5000'],
            'headline' => ['nullable', 'string', 'max:120'],
            'pronouns' => ['nullable', 'string', 'max:32'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'location' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'size:2', 'alpha'],
            'social_links' => ['nullable', 'array', 'max:6'],
            'social_links.*' => ['nullable', 'url', 'max:255'],
            'locale' => ['nullable', 'string', 'max:20'],
            'timezone' => ['nullable', 'timezone'],
            'profile_theme' => ['nullable', 'string', 'max:40'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'is_private' => ['nullable', 'boolean'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'remove_avatar' => ['nullable', 'boolean'],
            'remove_cover' => ['nullable', 'boolean'],
            'bio_html' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'Username may contain letters, numbers, and underscores only.',
            'username.min' => 'Username must be at least '.UsernameRules::minLength().' characters.',
            'username.max' => 'Username may not be greater than '.UsernameRules::maxLength().' characters.',
            'username.unique' => 'That username is already taken.',
            'country_code.alpha' => 'Country code must contain letters only.',
            'country_code.size' => 'Country code must be exactly 2 letters.',
            'bio_html.prohibited' => 'Bio HTML is generated automatically and cannot be submitted directly.',
            'avatar.image' => 'Avatar must be an image file.',
            'cover.image' => 'Cover must be an image file.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $username = UsernameNormalizer::normalize((string) $this->input('username'));

        $website = trim((string) $this->input('website'));
        if ($website !== '' && ! preg_match('/^https?:\/\//i', $website)) {
            $website = 'https://'.$website;
        }

        $countryCode = strtoupper(trim((string) $this->input('country_code')));
        $socialLinks = $this->input('social_links', []);

        if (! is_array($socialLinks)) {
            $socialLinks = [];
        }

        $normalizedSocialLinks = [];
        foreach ($socialLinks as $key => $value) {
            $cleanValue = trim((string) $value);

            if ($cleanValue === '') {
                continue;
            }

            if (! preg_match('/^https?:\\/\\//i', $cleanValue)) {
                $cleanValue = 'https://'.$cleanValue;
            }

            $normalizedSocialLinks[$key] = $cleanValue;
        }

        $this->merge([
            'username' => $username,
            'website' => $website !== '' ? $website : null,
            'country_code' => $countryCode !== '' ? $countryCode : null,
            'social_links' => $normalizedSocialLinks !== [] ? $normalizedSocialLinks : null,
        ]);
    }
}
