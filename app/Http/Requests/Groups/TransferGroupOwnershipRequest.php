<?php

namespace App\Http\Requests\Groups;

use App\Models\Groups\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferGroupOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');

        return $group instanceof Group
            && ($this->user()?->can('transferOwnership', $group) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'membership_id' => [
                'required',
                'integer',
                Rule::exists('group_members', 'id')->where('group_id', $this->route('group')?->getKey()),
            ],
        ];
    }
}
