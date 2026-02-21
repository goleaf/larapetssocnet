<?php

namespace App\Http\Requests;

use App\Policies\BlockPolicy;
use Illuminate\Foundation\Http\FormRequest;

class BlockUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        if (! $this->user() || ! $target) {
            return false;
        }

        $policy = app(BlockPolicy::class);

        if ($this->isMethod('delete')) {
            return $policy->unblock($this->user(), $target);
        }

        return $policy->block($this->user(), $target);
    }

    public function rules(): array
    {
        return [];
    }
}
