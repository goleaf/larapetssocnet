<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use App\Models\Identity\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCoverPositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('updateCover', $user);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'position' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'position.required' => 'Choose a cover focal point before saving.',
            'position.numeric' => 'Cover position must be a number.',
            'position.min' => 'Cover position must be between 0 and 100.',
            'position.max' => 'Cover position must be between 0 and 100.',
        ];
    }
}
