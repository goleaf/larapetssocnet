<?php

namespace App\Http\Requests;

use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;

class StoreGroupPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');

        return $group instanceof Group
            && ($this->user()?->can('post', $group) ?? false);
    }

    public function rules(): array
    {
        return [
            'post_id' => ['nullable', 'integer', 'exists:posts,id', 'required_without_all:body,media'],
            'body' => ['nullable', 'string', 'max:5000', 'required_without_all:post_id,media'],
            'media' => ['nullable', 'array', 'max:4', 'required_without_all:post_id,body'],
            'media.*' => [
                'file',
                'max:51200',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/webm',
            ],
        ];
    }
}
