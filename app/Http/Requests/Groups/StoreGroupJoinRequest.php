<?php

declare(strict_types=1);

namespace App\Http\Requests\Groups;

use App\Models\Groups\Group;
use Illuminate\Foundation\Http\FormRequest;

class StoreGroupJoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');

        return $group instanceof Group && $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:500'],
        ];
    }
}
