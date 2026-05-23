<?php

namespace App\Actions\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\Identity\User;
use App\Services\Auth\AuthAuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AttemptLoginAction
{
    public function __construct(private readonly AuthAuditLogger $auditLogger) {}

    /**
     * @throws ValidationException
     */
    public function handle(LoginRequest $request): ?RedirectResponse
    {
        $identifier = $request->normalizedIdentifier();
        $candidateUser = $this->candidateUser($identifier);

        try {
            $request->ensureIsNotRateLimited();
        } catch (ValidationException $exception) {
            $this->recordFailure($request, $candidateUser, $identifier, 'rate_limited');

            throw $exception;
        }

        if (! $candidateUser instanceof User || ! Hash::check((string) $request->input('password'), (string) $candidateUser->password)) {
            $this->failLogin($request, $candidateUser, $identifier, 'invalid_credentials');
        }

        if ($candidateUser->trashed()) {
            $this->failLogin($request, $candidateUser, $identifier, 'deleted');
        }

        if ((bool) $candidateUser->is_banned) {
            RateLimiter::hit($request->throttleKey());
            $this->recordFailure($request, $candidateUser, $identifier, 'banned');

            return redirect()->route('banned')->with('status', 'account-restricted');
        }

        if ($candidateUser->scheduled_deletion_at !== null) {
            return $this->restrictedLogin($request, $candidateUser, $identifier, 'scheduled_deletion', 'account.deletion-pending');
        }

        if ($candidateUser->deactivated_at !== null) {
            return $this->restrictedLogin($request, $candidateUser, $identifier, 'deactivated', 'account.reactivation');
        }

        if ($this->userIsSuspended($candidateUser)) {
            return $this->restrictedLogin($request, $candidateUser, $identifier, 'suspended', 'account.suspended');
        }

        if (Hash::needsRehash((string) $candidateUser->password)) {
            $candidateUser->forceFill([
                'password' => Hash::make((string) $request->input('password')),
            ])->save();
        }

        Auth::login($candidateUser, $request->boolean('remember'));

        RateLimiter::clear($request->throttleKey());
        $requiresTwoFactorChallenge = $candidateUser->two_factor_secret !== null;

        if ($requiresTwoFactorChallenge) {
            $request->session()->put('auth.two_factor_pending_user_id', $candidateUser->getKey());
        } else {
            $request->session()->forget('auth.two_factor_pending_user_id');
        }

        $updates = [
            'last_active_at' => now(),
        ];

        if (! $requiresTwoFactorChallenge) {
            $updates['last_login_at'] = now();
        }

        if ($this->tracksFailedLogins()) {
            $updates['failed_login_attempts'] = 0;
            $updates['last_failed_login_at'] = null;
        }

        $candidateUser->forceFill($updates)->save();

        $this->auditLogger->record($request->user(), 'login_success', $request, [
            'identifier_type' => $this->identifierType($identifier),
            'remember' => $request->boolean('remember'),
            'restricted_to_verification' => ! $candidateUser->hasVerifiedEmail(),
            'two_factor_required' => $requiresTwoFactorChallenge,
        ]);

        return null;
    }

    private function candidateUser(string $identifier): ?User
    {
        if ($this->identifierType($identifier) === 'email') {
            return User::withTrashed()->where('email', $identifier)->first();
        }

        return User::withTrashed()->where('username', $identifier)->first();
    }

    /**
     * @throws ValidationException
     */
    private function failLogin(LoginRequest $request, ?User $candidateUser, string $identifier, string $reason): never
    {
        RateLimiter::hit($request->throttleKey());

        $this->recordFailure($request, $candidateUser, $identifier, $reason);

        throw ValidationException::withMessages([
            'email' => trans('auth.failed'),
        ]);
    }

    private function restrictedLogin(LoginRequest $request, User $user, string $identifier, string $reason, string $route): RedirectResponse
    {
        Auth::login($user, false);

        $this->auditLogger->record($user, 'login_restricted', $request, [
            'identifier_type' => $this->identifierType($identifier),
            'restriction_reason' => $reason,
            'identifier_hash' => hash('sha256', $identifier),
        ]);

        return redirect()->route($route);
    }

    private function recordFailure(LoginRequest $request, ?User $candidateUser, string $identifier, string $reason): void
    {
        if ($candidateUser instanceof User && $this->tracksFailedLogins()) {
            $candidateUser->forceFill([
                'failed_login_attempts' => max(0, (int) $candidateUser->failed_login_attempts) + 1,
                'last_failed_login_at' => now(),
            ])->saveQuietly();
        }

        $this->auditLogger->record($candidateUser, 'login_failure', $request, [
            'identifier_type' => $this->identifierType($identifier),
            'identifier_hash' => hash('sha256', $identifier),
            'failure_reason' => $reason,
            'rate_limited' => RateLimiter::tooManyAttempts($request->throttleKey(), 5),
        ]);
    }

    private function userIsSuspended(User $user): bool
    {
        $suspendedUntil = $user->getAttribute('suspended_until');

        return $suspendedUntil instanceof CarbonInterface && $suspendedUntil->isFuture();
    }

    private function identifierType(string $identifier): string
    {
        return filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false ? 'email' : 'username';
    }

    private function tracksFailedLogins(): bool
    {
        return Schema::hasColumn('users', 'failed_login_attempts')
            && Schema::hasColumn('users', 'last_failed_login_at');
    }
}
