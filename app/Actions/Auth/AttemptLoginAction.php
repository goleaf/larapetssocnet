<?php

namespace App\Actions\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\Identity\User;
use App\Services\Auth\AuthAuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AttemptLoginAction
{
    public function __construct(private readonly AuthAuditLogger $auditLogger) {}

    /**
     * @throws ValidationException
     */
    public function handle(LoginRequest $request): void
    {
        $request->ensureIsNotRateLimited();

        $identifier = trim((string) $request->input('email'));
        $candidateUser = $this->candidateUser($identifier);

        if (! Auth::attempt($this->credentialsFor($identifier, (string) $request->input('password')), $request->boolean('remember'))) {
            RateLimiter::hit($request->throttleKey());

            $this->auditLogger->record($candidateUser, 'login_failure', $request, [
                'identifier_type' => $this->identifierType($identifier),
            ]);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($request->throttleKey());
        $this->auditLogger->record($request->user(), 'login_success', $request, [
            'identifier_type' => $this->identifierType($identifier),
        ]);
    }

    /**
     * @return array{password: string, email?: string, username?: string}
     */
    private function credentialsFor(string $identifier, string $password): array
    {
        if ($this->identifierType($identifier) === 'email') {
            return [
                'email' => strtolower($identifier),
                'password' => $password,
            ];
        }

        return [
            'username' => User::normalizeUsername($identifier),
            'password' => $password,
        ];
    }

    private function candidateUser(string $identifier): ?User
    {
        if ($this->identifierType($identifier) === 'email') {
            return User::query()->where('email', strtolower($identifier))->first();
        }

        return User::query()->where('username', User::normalizeUsername($identifier))->first();
    }

    private function identifierType(string $identifier): string
    {
        return filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false ? 'email' : 'username';
    }
}
