<?php

namespace App\Actions\Auth;

use App\Mail\Auth\PasswordResetLinkMail;
use App\Models\Identity\User;
use App\Services\Auth\AuthAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class RequestPasswordResetLinkAction
{
    public const string RESPONSE_MESSAGE = 'If an account with that email exists, you will receive a password reset link shortly.';

    private const int MAX_ATTEMPTS = 3;

    private const int DECAY_SECONDS = 3600;

    public function __construct(private readonly AuthAuditLogger $auditLogger) {}

    public function handle(string $email, Request $request, string $source = 'password_reset_request'): string
    {
        $email = Str::lower(trim($email));
        $rateLimitKey = $this->rateLimitKey($email);

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::MAX_ATTEMPTS)) {
            $this->auditLogger->record(null, 'password_reset_requested', $request, [
                'identifier_hash' => hash('sha256', $email),
                'matched_user' => false,
                'rate_limited' => true,
                'source' => $source,
            ]);

            return self::RESPONSE_MESSAGE;
        }

        RateLimiter::hit($rateLimitKey, self::DECAY_SECONDS);

        $user = User::query()
            ->select(['id', 'name', 'email', 'password'])
            ->where('email', $email)
            ->first();

        if ($user instanceof User) {
            $token = Password::broker()->createToken($user);

            DB::table($this->passwordResetTable())
                ->where('email', $email)
                ->update([
                    'token_hash' => hash('sha256', $token),
                ]);

            Mail::to($user->email)->queue(new PasswordResetLinkMail(
                user: $user,
                resetUrl: route('password.reset', ['token' => $token]),
            ));
        }

        $this->auditLogger->record($user, 'password_reset_requested', $request, [
            'identifier_hash' => hash('sha256', $email),
            'matched_user' => $user instanceof User,
            'rate_limited' => false,
            'source' => $source,
        ]);

        return self::RESPONSE_MESSAGE;
    }

    public function rateLimitKey(string $email): string
    {
        return 'password-reset-request:email:'.hash('sha256', Str::lower(trim($email)));
    }

    private function passwordResetTable(): string
    {
        return (string) config('auth.passwords.users.table', 'password_reset_tokens');
    }
}
