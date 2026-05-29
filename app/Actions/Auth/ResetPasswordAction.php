<?php

namespace App\Actions\Auth;

use App\Models\Identity\User;
use App\Models\Security\AccountSecurityAction;
use App\Services\Auth\AuthAuditLogger;
use App\Services\Auth\AuthMailDispatcher;
use App\Services\Auth\DeviceSessionService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;

class ResetPasswordAction
{
    public const string INVALID_LINK_MESSAGE = 'That password reset link is invalid or has already been used. Request a new link to continue.';

    public const string EXPIRED_LINK_MESSAGE = 'That password reset link has expired. Request a new link to continue.';

    public const string SUCCESS_MESSAGE = 'Your password has been updated successfully.';

    public function __construct(
        private readonly AuthAuditLogger $auditLogger,
        private readonly DeviceSessionService $sessions,
        private readonly AuthMailDispatcher $mailDispatcher,
    ) {}

    public function findTokenRecord(string $token): ?stdClass
    {
        $record = DB::table($this->passwordResetTable())
            ->select(['email', 'token', 'token_hash', 'created_at'])
            ->where('token_hash', hash('sha256', $token))
            ->first();

        return $record instanceof stdClass ? $record : null;
    }

    public function tokenExpired(stdClass $record): bool
    {
        if (! is_string($record->created_at)) {
            return true;
        }

        return CarbonImmutable::parse($record->created_at)
            ->addMinutes($this->expirationMinutes())
            ->isPast();
    }

    public function reset(string $token, string $email, string $password, Request $request): User
    {
        $updatedUser = null;
        $changedAt = now();

        $status = Password::broker()->reset([
            'email' => Str::lower(trim($email)),
            'password' => $password,
            'password_confirmation' => $password,
            'token' => $token,
        ], function (User $user) use ($password, $request, $changedAt, &$updatedUser): void {
            $plainEmergencyToken = Str::random(64);

            $securityAction = AccountSecurityAction::query()->create([
                'user_id' => $user->getKey(),
                'action_type' => AccountSecurityAction::ACTION_PASSWORD_RESET_EMERGENCY_LOCK,
                'token_hash' => hash('sha256', $plainEmergencyToken),
                'expires_at' => null,
            ]);

            $emergencyUrl = URL::signedRoute('password.security-lock', [
                'action' => $securityAction->getKey(),
                'token' => $plainEmergencyToken,
            ]);

            $user->forceFill([
                'password' => Hash::make($password),
                'password_changed_at' => $changedAt,
                'remember_token' => null,
                'failed_login_attempts' => 0,
                'last_failed_login_at' => null,
            ])->save();

            $deletedSessions = $this->sessions->destroyAllSessions($user);

            event(new PasswordReset($user));

            $this->auditLogger->record($user, 'password_reset', $request, [
                'deleted_sessions' => $deletedSessions,
                'security_action_id' => $securityAction->getKey(),
            ]);

            $this->mailDispatcher->queuePasswordChangedSecurityAlert($user, $emergencyUrl, $changedAt);

            $updatedUser = $user;
        });

        if ($status !== Password::PASSWORD_RESET || ! $updatedUser instanceof User) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $updatedUser;
    }

    private function passwordResetTable(): string
    {
        return (string) config('auth.passwords.users.table', 'password_reset_tokens');
    }

    private function expirationMinutes(): int
    {
        return (int) config('auth.passwords.users.expire', 60);
    }
}
