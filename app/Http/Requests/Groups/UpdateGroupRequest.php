<?php

namespace App\Http\Requests\Groups;

use App\Models\Groups\Group;
use App\Rules\ReservedGroupSlugRule;
use App\Services\GroupSlugService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');

        return $group instanceof Group
            && ($this->user()?->can('update', $group) ?? false);
    }

    public function rules(): array
    {
        /** @var Group|null $group */
        $group = $this->route('group');

        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'min:3', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', new ReservedGroupSlugRule(app(GroupSlugService::class)), Rule::unique('groups', 'slug')->ignore($group)->withoutTrashed()],
            'description' => ['nullable', 'string', 'max:5000'],
            'rules' => ['nullable', 'string', 'max:5000'],
            'privacy' => ['required', Rule::in(['public', 'private', 'secret'])],
            'location' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:200'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
            'cover' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');
        $normalizedSlug = $slug !== null ? app(GroupSlugService::class)->normalize((string) $slug) : null;

        $this->merge([
            'slug' => $normalizedSlug,
            'description' => $this->normalizeNullableString($this->input('description')),
            'rules' => $this->normalizeNullableString($this->input('rules')),
        ]);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
