<?php

namespace App\Http\Requests\Pets;

use App\Models\Pets\Pet;
use App\Models\Pets\PetMilestone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePetMilestoneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $pet = $this->route('pet');

        if (! $pet instanceof Pet) {
            return false;
        }

        return $this->user()?->can('manageMilestones', $pet) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'milestone_type' => ['nullable', 'string', Rule::in(PetMilestone::TYPES)],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:1000'],
            'occurred_on' => ['required', 'date', 'before_or_equal:today'],
            'share_as_post' => ['nullable', 'boolean'],
        ];
    }
}
