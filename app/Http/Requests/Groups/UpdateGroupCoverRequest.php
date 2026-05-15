<?php

namespace App\Http\Requests\Groups;

use App\Models\Groups\Group;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupCoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');

        return $group instanceof Group
            && ($this->user()?->can('manageCover', $group) ?? false);
    }

    public function rules(): array
    {
        return [
            'cover' => ['required', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
        ];
    }
}
