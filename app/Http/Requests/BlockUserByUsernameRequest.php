<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Policies\BlockPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class BlockUserByUsernameRequest extends FormRequest
{
    private ?User $targetUser = null;

    private string $authorizationMessage = 'You cannot block this user.';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor) {
            $this->authorizationMessage = 'You must be signed in to block users.';

            return false;
        }

        $target = $this->resolveTarget();

        if (! $target) {
            $this->authorizationMessage = 'This user could not be found.';

            return false;
        }

        if (! app(BlockPolicy::class)->block($actor, $target)) {
            if ($actor->is($target)) {
                $this->authorizationMessage = 'You cannot block yourself.';
            } elseif ($target->hasAnyRole(['admin', 'moderator'])) {
                $this->authorizationMessage = 'You cannot block an admin or moderator.';
            }

            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'exists:users,username'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Enter a username to block.',
            'username.exists' => 'We could not find that user.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => User::normalizeUsername((string) $this->input('username')),
        ]);
    }

    protected function failedAuthorization(): void
    {
        throw ValidationException::withMessages([
            'username' => $this->authorizationMessage,
        ]);
    }

    public function target(): User
    {
        $target = $this->resolveTarget();

        if (! $target) {
            throw ValidationException::withMessages([
                'username' => 'We could not find that user.',
            ]);
        }

        return $target;
    }

    private function resolveTarget(): ?User
    {
        if ($this->targetUser) {
            return $this->targetUser;
        }

        $username = (string) $this->input('username');

        if ($username === '') {
            return null;
        }

        $this->targetUser = User::query()->where('username', $username)->first();

        return $this->targetUser;
    }
}
