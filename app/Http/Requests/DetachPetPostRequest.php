<?php

namespace App\Http\Requests;

use App\Models\Pet;
use App\Models\Post;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DetachPetPostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $pet = $this->route('pet');
        $post = $this->route('post');

        if (! $pet instanceof Pet || ! $post instanceof Post) {
            return false;
        }

        return $this->user()?->can('detachPost', [$pet, $post]) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
        ];
    }
}
