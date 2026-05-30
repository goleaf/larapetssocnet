<?php

namespace App\Http\Requests\Social;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFeedMuteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mutable_type' => ['required', 'string', Rule::in(['user', 'pet'])],
            'mutable_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $target = $this->target();

            if (! $target instanceof Model) {
                $validator->errors()->add('mutable_id', 'Choose an account or pet to mute.');

                return;
            }

            if ($target instanceof User && $this->user()?->is($target)) {
                $validator->errors()->add('mutable_id', 'You cannot mute yourself.');
            }
        });
    }

    public function target(): ?Model
    {
        return match ((string) $this->input('mutable_type')) {
            'user' => User::query()->whereKey((int) $this->input('mutable_id'))->first(),
            'pet' => Pet::query()->whereKey((int) $this->input('mutable_id'))->first(),
            default => null,
        };
    }

    public function targetLabel(): string
    {
        $target = $this->target();

        if ($target instanceof User) {
            return '@'.($target->username ?: $target->name);
        }

        if ($target instanceof Pet) {
            return (string) $target->name;
        }

        return 'that source';
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'mutable_type' => strtolower((string) $this->input('mutable_type')),
        ]);
    }
}
