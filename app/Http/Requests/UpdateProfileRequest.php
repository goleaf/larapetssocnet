<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\NotReservedUsername;
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
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-zA-Z0-9_]+$/',
                new NotReservedUsername,
                Rule::unique('users', 'username')->ignore($userId),
            ],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'bio' => ['nullable', 'string', 'max:5000'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'location' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'size:2', 'alpha'],
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
        $username = User::normalizeUsername((string) $this->input('username'));

        $website = trim((string) $this->input('website'));
        if ($website !== '' && ! preg_match('/^https?:\/\//i', $website)) {
            $website = 'https://'.$website;
        }

        $countryCode = strtoupper(trim((string) $this->input('country_code')));

        $this->merge([
            'username' => $username,
            'website' => $website !== '' ? $website : null,
            'country_code' => $countryCode !== '' ? $countryCode : null,
        ]);
    }
}
