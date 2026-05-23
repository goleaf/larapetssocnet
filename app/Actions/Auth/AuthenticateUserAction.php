<?php

namespace App\Actions\Auth;

use App\Enums\AccountStatus;
use App\Models\Identity\User;
use App\Services\Auth\AuthAuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthenticateUserAction
{
    private const string GENERIC_FAILURE_MESSAGE = 'These credentials do not match our records.';

    private const int FAILURE_WINDOW_MINUTES = 15;

    private const int LOCKOUT_THRESHOLD = 5;

    private const int BASE_LOCKOUT_SECONDS = 60;

    private const int MAX_LOCKOUT_SECONDS = 1800;

    public function __construct(private readonly AuthAuditLogger $auditLogger) {}

    public function handle(string $credential, string $password, bool $remember, Request $request): AuthenticationResult
    {
        $rawCredential = trim($credential);
        $type = $this->credentialType($rawCredential);
        $identifier = $this->normalizeIdentifier($rawCredential, $type);
        $user = $this->candidateUser($identifier, $type);

        if ($user instanceof User) {
            $this->resetExpiredFailureWindow($user);

            $lockoutSeconds = $this->activeLockoutSeconds($user);

            if ($lockoutSeconds > 0) {
                $this->recordFailure($user, $identifier, $type, 'locked_out', $request, [
                    'lockout_seconds' => $lockoutSeconds,
                ]);

                return AuthenticationResult::lockedOut($lockoutSeconds);
            }
        }

        if (! $user instanceof User || ! Hash::check($password, (string) $user->password)) {
            return $this->failedCredentialAttempt($user, $identifier, $type, $request);
        }

        if ($user->trashed()) {
            $this->incrementFailedAttempts($user);
            $this->recordFailure($user, $identifier, $type, 'deleted', $request);

            return AuthenticationResult::failure('invalid_credentials', self::GENERIC_FAILURE_MESSAGE);
        }

        if ((bool) $user->is_banned) {
            $this->incrementFailedAttempts($user);
            $this->recordFailure($user, $identifier, $type, 'banned', $request);

            return AuthenticationResult::restricted($user, 'banned', 'banned');
        }

        $restrictedRoute = $this->restrictedRouteFor($user);

        if ($restrictedRoute !== null) {
            Auth::login($user, false);

            $this->auditLogger->record($user, 'login_restricted', $request, [
                'identifier_type' => $type,
                'restriction_reason' => $restrictedRoute['reason'],
                'identifier_hash' => hash('sha256', $identifier),
            ]);

            return AuthenticationResult::restricted($user, $restrictedRoute['reason'], $restrictedRoute['route']);
        }

        $statusFailure = $this->statusFailure($user);

        if ($statusFailure !== null) {
            $this->recordFailure($user, $identifier, $type, $statusFailure['reason'], $request);

            return AuthenticationResult::failure($statusFailure['reason'], $statusFailure['message']);
        }

        if (Hash::needsRehash((string) $user->password)) {
            $user->forceFill([
                'password' => Hash::make($password),
            ])->save();
        }

        Auth::login($user, $remember);

        $requiresTwoFactor = $user->two_factor_secret !== null;

        if ($requiresTwoFactor) {
            session()->put('auth.two_factor_pending_user_id', $user->getKey());
        } else {
            session()->forget('auth.two_factor_pending_user_id');
        }

        $updates = [
            'failed_login_attempts' => 0,
            'last_failed_login_at' => null,
            'last_active_at' => now(),
        ];

        if (! $requiresTwoFactor) {
            $updates['last_login_at'] = now();
        }

        $user->forceFill($updates)->save();

        $this->auditLogger->record($user, 'login_success', $request, [
            'identifier_type' => $type,
            'identifier_hash' => hash('sha256', $identifier),
            'remember' => $remember,
            'restricted_to_verification' => ! $user->hasVerifiedEmail(),
            'two_factor_required' => $requiresTwoFactor,
        ]);

        return AuthenticationResult::success($user, $requiresTwoFactor);
    }

    private function failedCredentialAttempt(?User $user, string $identifier, string $type, Request $request): AuthenticationResult
    {
        if (! $user instanceof User) {
            $this->recordFailure(null, $identifier, $type, 'invalid_credentials', $request);

            return AuthenticationResult::failure('invalid_credentials', self::GENERIC_FAILURE_MESSAGE);
        }

        $this->incrementFailedAttempts($user);
        $lockoutSeconds = $this->activeLockoutSeconds($user);

        $this->recordFailure($user, $identifier, $type, $lockoutSeconds > 0 ? 'lockout_applied' : 'invalid_credentials', $request, [
            'lockout_seconds' => $lockoutSeconds > 0 ? $lockoutSeconds : null,
        ]);

        if ($lockoutSeconds > 0) {
            return AuthenticationResult::lockedOut($lockoutSeconds);
        }

        return AuthenticationResult::failure('invalid_credentials', self::GENERIC_FAILURE_MESSAGE);
    }

    private function candidateUser(string $identifier, string $type): ?User
    {
        $query = User::withTrashed()
            ->select([
                'id',
                'name',
                'email',
                'email_verified_at',
                'password',
                'remember_token',
                'username',
                'account_status',
                'is_banned',
                'scheduled_deletion_at',
                'deactivated_at',
                'suspended_until',
                'two_factor_secret',
                'failed_login_attempts',
                'last_failed_login_at',
                'last_active_at',
                'last_login_at',
                'onboarding_step',
                'onboarding_completed_at',
                'deleted_at',
            ]);

        if ($type === 'email') {
            $query->where('email', $identifier);
        } else {
            $query->whereRaw('username = ? COLLATE NOCASE', [$identifier]);
        }

        return $query->first();
    }

    private function credentialType(string $credential): string
    {
        return str_contains($credential, '@') && filter_var($credential, FILTER_VALIDATE_EMAIL) !== false
            ? 'email'
            : 'username';
    }

    private function normalizeIdentifier(string $credential, string $type): string
    {
        if ($type === 'email') {
            return Str::lower($credential);
        }

        return User::normalizeUsername($credential);
    }

    private function resetExpiredFailureWindow(User $user): void
    {
        $lastFailedLoginAt = $this->lastFailedLoginAt($user);

        if (! $lastFailedLoginAt instanceof CarbonInterface) {
            return;
        }

        if ($lastFailedLoginAt->greaterThanOrEqualTo(now()->subMinutes(self::FAILURE_WINDOW_MINUTES))) {
            return;
        }

        $user->forceFill([
            'failed_login_attempts' => 0,
            'last_failed_login_at' => null,
        ])->saveQuietly();
    }

    private function incrementFailedAttempts(User $user): void
    {
        $attempts = max(0, (int) $user->failed_login_attempts) + 1;

        $user->forceFill([
            'failed_login_attempts' => $attempts,
            'last_failed_login_at' => now(),
        ])->saveQuietly();
    }

    private function activeLockoutSeconds(User $user): int
    {
        $lastFailedLoginAt = $this->lastFailedLoginAt($user);

        if ((int) $user->failed_login_attempts < self::LOCKOUT_THRESHOLD || ! $lastFailedLoginAt instanceof CarbonInterface) {
            return 0;
        }

        if ($lastFailedLoginAt->lessThan(now()->subMinutes(self::FAILURE_WINDOW_MINUTES))) {
            return 0;
        }

        $expiresAt = $lastFailedLoginAt->copy()->addSeconds(
            $this->lockoutDurationSeconds((int) $user->failed_login_attempts),
        );

        return max(0, $expiresAt->getTimestamp() - now()->getTimestamp());
    }

    private function lockoutDurationSeconds(int $failedAttempts): int
    {
        $lockoutNumber = max(1, intdiv($failedAttempts, self::LOCKOUT_THRESHOLD));
        $exponent = max(0, $lockoutNumber - 1);

        return min(self::MAX_LOCKOUT_SECONDS, self::BASE_LOCKOUT_SECONDS * (2 ** $exponent));
    }

    /**
     * @return array{reason: string, route: string}|null
     */
    private function restrictedRouteFor(User $user): ?array
    {
        if ($user->scheduled_deletion_at !== null) {
            return ['reason' => 'scheduled_deletion', 'route' => 'account.deletion-pending'];
        }

        if ($user->deactivated_at !== null) {
            return ['reason' => 'deactivated', 'route' => 'account.reactivation'];
        }

        if ($this->userIsSuspended($user)) {
            return ['reason' => 'suspended', 'route' => 'account.suspended'];
        }

        return null;
    }

    /**
     * @return array{reason: string, message: string}|null
     */
    private function statusFailure(User $user): ?array
    {
        if ($user->hasAccountStatus(AccountStatus::Deactivated)) {
            return [
                'reason' => 'deactivated',
                'message' => 'This account is deactivated. Reactivation is required before signing in.',
            ];
        }

        if ($user->hasAccountStatus(AccountStatus::Suspended)) {
            return [
                'reason' => 'suspended',
                'message' => 'This account is suspended and cannot sign in right now.',
            ];
        }

        if ($user->hasAccountStatus(AccountStatus::PendingDeletion)) {
            return [
                'reason' => 'pending_deletion',
                'message' => 'This account is pending deletion. Contact support if you need help recovering it.',
            ];
        }

        return null;
    }

    private function userIsSuspended(User $user): bool
    {
        $suspendedUntil = $user->getAttribute('suspended_until');

        return $suspendedUntil instanceof CarbonInterface && $suspendedUntil->isFuture();
    }

    private function lastFailedLoginAt(User $user): ?CarbonInterface
    {
        $lastFailedLoginAt = $user->getAttribute('last_failed_login_at');

        return $lastFailedLoginAt instanceof CarbonInterface ? $lastFailedLoginAt : null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordFailure(?User $user, string $identifier, string $type, string $reason, Request $request, array $metadata = []): void
    {
        $this->auditLogger->record($user, 'login_failure', $request, array_filter([
            'identifier_type' => $type,
            'identifier_hash' => hash('sha256', $identifier),
            'failure_reason' => $reason,
            ...$metadata,
        ], static fn (mixed $value): bool => $value !== null));
    }
}
