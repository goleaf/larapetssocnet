<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:2000'],
            'pet_id' => [
                'nullable',
                'integer',
                Rule::exists('pets', 'id')->where(
                    fn ($query) => $query->where('user_id', (int) $this->user()?->id)
                ),
            ],
            'tagged_pets' => ['nullable', 'array'],
            'tagged_pets.*' => [
                'integer',
                Rule::exists('pets', 'id')->where(
                    fn ($query) => $query->where('user_id', (int) $this->user()?->id)
                ),
            ],
            'visibility' => ['nullable', 'string', 'in:public,followers,private'],
            'location' => ['nullable', 'string', 'max:100'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpeg,png,gif,webp', 'max:20480'],
            'video' => ['nullable', 'file', 'mimes:mp4,mov', 'max:20480'],
            'media' => ['nullable', 'array'],
            'media.*' => [
                'file',
                File::types(['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov'])->max('20mb'),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->hasFile('video') && is_array($this->file('photos')) && count($this->file('photos')) > 0) {
                $validator->errors()->add('video', 'Video cannot be uploaded together with photos.');
            }

            if (
                $this->hasFile('media')
                && ($this->hasFile('video') || (is_array($this->file('photos')) && count($this->file('photos')) > 0))
            ) {
                $validator->errors()->add('media', 'Use either media[] uploads or legacy photos/video fields, not both.');
            }
        });
    }
}
