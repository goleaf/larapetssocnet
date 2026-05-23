<?php

namespace App\Http\Requests\Settings;

use App\Enums\ProfileTheme;
use App\Models\Identity\User;
use App\Support\Profiles\SocialLinkNormalizer;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $user = $this->user();
        $userId = $user?->getAuthIdentifier();
        $currentUsername = (string) ($user?->username ?? '');
        $incomingUsername = (string) $this->input('username');

        return [
            'name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:120'],
            'username' => UsernameRules::requiredRules(is_numeric($userId) ? (int) $userId : null),
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($userId),
            ],
            'bio' => ['nullable', 'string', 'max:1000'],
            'headline' => ['nullable', 'string', 'max:120'],
            'pronouns' => ['nullable', 'string', 'max:32'],
            'location' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'profile_theme' => ['nullable', Rule::enum(ProfileTheme::class)],
            'social_links' => ['nullable', 'array', 'max:4'],
            'social_links.x' => ['nullable', 'string', 'max:16', 'regex:/^@[A-Za-z0-9_]{1,15}$/'],
            'social_links.instagram' => ['nullable', 'string', 'max:31', 'regex:/^@[A-Za-z0-9](?:[A-Za-z0-9._]{0,28}[A-Za-z0-9])?$/'],
            'social_links.facebook' => ['nullable', 'url:http,https', 'max:255'],
            'social_links.youtube' => ['nullable', 'url:http,https', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'in:male,female,other,prefer_not_to_say'],
            'locale' => ['nullable', 'string', 'max:20'],
            'timezone' => ['nullable', 'timezone'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'remove_avatar' => ['nullable', 'boolean'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120', 'dimensions:min_width=1200,min_height=400'],
            'remove_cover' => ['nullable', 'boolean'],
            'bio_html' => ['prohibited'],
            'username_confirm' => [
                Rule::requiredIf($incomingUsername !== '' && $incomingUsername !== $currentUsername),
                'nullable',
                'string',
                Rule::in([$currentUsername]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username_confirm.in' => 'You must type your CURRENT username exactly to confirm the change.',
            'username_confirm.required' => 'Confirming your current username is required.',
            'website.url' => 'Enter a valid website URL.',
            'profile_theme.enum' => 'Choose one of the available profile themes.',
            'avatar.image' => 'Avatar must be an image file.',
            'avatar.mimes' => 'Avatar must be a JPG, PNG, or WEBP image.',
            'avatar.max' => 'Avatar must be smaller than 3MB.',
            'cover.image' => 'Cover must be an image file.',
            'cover.mimes' => 'Cover must be a JPG, PNG, WEBP, or GIF image.',
            'cover.max' => 'Cover must be smaller than 5MB.',
            'cover.dimensions' => 'Cover photo must be at least 1200 by 400 pixels.',
            'bio_html.prohibited' => 'Bio HTML is generated automatically and cannot be submitted directly.',
            'social_links.x.regex' => 'Enter a valid Twitter/X username.',
            'social_links.instagram.regex' => 'Enter a valid Instagram username.',
            'social_links.facebook.url' => 'Enter a valid Facebook profile URL.',
            'social_links.youtube.url' => 'Enter a valid YouTube channel URL.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $username = UsernameNormalizer::normalize((string) $this->input('username'));
        $website = trim((string) $this->input('website'));
        $profileTheme = trim((string) $this->input('profile_theme'));
        $normalizedSocialLinks = SocialLinkNormalizer::normalizeInputs($this->input('social_links', []));

        if ($website !== '' && ! preg_match('/^https?:\\/\\//i', $website)) {
            $website = 'https://'.$website;
        }

        $payload = [
            'username' => $username,
            'website' => $website !== '' ? $website : null,
            'social_links' => $normalizedSocialLinks !== [] ? $normalizedSocialLinks : null,
        ];

        if ($this->has('profile_theme')) {
            $payload['profile_theme'] = $profileTheme !== '' ? $profileTheme : null;
        }

        $this->merge($payload);
    }
}
