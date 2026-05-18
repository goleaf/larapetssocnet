<?php

namespace App\Http\Requests\Settings;

use App\Models\Identity\User;
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
            'social_links' => ['nullable', 'array', 'max:6'],
            'social_links.*' => ['nullable', 'url', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'in:male,female,other,prefer_not_to_say'],
            'locale' => ['nullable', 'string', 'max:20'],
            'timezone' => ['nullable', 'timezone'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'remove_avatar' => ['nullable', 'boolean'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
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
            'avatar.image' => 'Avatar must be an image file.',
            'avatar.mimes' => 'Avatar must be a JPG, PNG, or WEBP image.',
            'avatar.max' => 'Avatar must be smaller than 10MB.',
            'cover.image' => 'Cover must be an image file.',
            'cover.mimes' => 'Cover must be a JPG, PNG, WEBP, or GIF image.',
            'cover.max' => 'Cover must be smaller than 5MB.',
            'bio_html.prohibited' => 'Bio HTML is generated automatically and cannot be submitted directly.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $username = UsernameNormalizer::normalize((string) $this->input('username'));
        $website = trim((string) $this->input('website'));
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

        if ($website !== '' && ! preg_match('/^https?:\\/\\//i', $website)) {
            $website = 'https://'.$website;
        }

        $this->merge([
            'username' => $username,
            'website' => $website !== '' ? $website : null,
            'social_links' => $normalizedSocialLinks !== [] ? $normalizedSocialLinks : null,
        ]);
    }
}
