<?php

namespace App\Http\Requests\Moderation;

use App\Models\Identity\User;
use App\Models\Moderation\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StoreProfileReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $target = $this->targetUser();

        return $actor instanceof User
            && $target instanceof User
            && Gate::forUser($actor)->allows('report', $target);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return self::rulesForValidation();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::messagesForValidation();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(self::normalize($this->all()));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{reason: string, details?: string|null}
     */
    public static function validateForLivewire(User $target, User $actor, array $input): array
    {
        Gate::forUser($actor)->authorize('report', $target);

        /** @var array{reason: string, details?: string|null} $validated */
        $validated = Validator::make(
            self::normalize($input),
            self::rulesForValidation(),
            self::messagesForValidation(),
        )->validate();

        return $validated;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function rulesForValidation(): array
    {
        return [
            'reason' => ['required', 'string', Rule::in(Report::PROFILE_REASONS)],
            'details' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messagesForValidation(): array
    {
        return [
            'reason.required' => 'Choose why you are reporting this profile.',
            'reason.in' => 'Choose a valid profile report reason.',
            'details.max' => 'Additional context must be 500 characters or fewer.',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $input['reason'] = strtolower(trim((string) ($input['reason'] ?? '')));

        $details = trim((string) ($input['details'] ?? ''));
        $input['details'] = $details !== '' ? $details : null;

        return $input;
    }

    private function targetUser(): ?User
    {
        $target = $this->route('user');

        return $target instanceof User ? $target : null;
    }
}
