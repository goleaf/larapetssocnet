<?php

namespace App\Actions\Auth;

use App\Mail\Auth\MagicLoginLinkMail;
use App\Models\Identity\User;
use App\Services\Auth\AuthAuditLogger;
use App\Services\Auth\MagicLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class RequestMagicLoginLinkAction
{
    public const string RESPONSE_MESSAGE = 'If an account with that email exists, you will receive a login link shortly.';

    private const int MAX_ATTEMPTS = 5;

    private const int DECAY_SECONDS = 3600;

    public function __construct(
        private readonly AuthAuditLogger $auditLogger,
        private readonly MagicLinkService $magicLinks,
    ) {}

    public function handle(string $email, Request $request, string $source = 'magic_login_request'): string
    {
        $email = Str::lower(trim($email));
        $rateLimitKey = $this->rateLimitKey($email);
        $identifierHash = hash('sha256', $email);

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::MAX_ATTEMPTS)) {
            $this->auditLogger->record(null, 'magic_link_requested', $request, [
                'identifier_hash' => $identifierHash,
                'matched_user' => false,
                'rate_limited' => true,
                'source' => $source,
            ]);

            return self::RESPONSE_MESSAGE;
        }

        RateLimiter::hit($rateLimitKey, self::DECAY_SECONDS);

        $user = User::query()
            ->select(['id', 'name', 'email'])
            ->where('email', $email)
            ->first();

        if ($user instanceof User) {
            [$magicToken, $plainToken] = $this->magicLinks->create($user);

            Mail::to($user->email)->queue(new MagicLoginLinkMail(
                user: $user,
                loginUrl: route('magic-login.consume', ['token' => $plainToken]),
            ));
        }

        $this->auditLogger->record($user, 'magic_link_requested', $request, [
            'identifier_hash' => $identifierHash,
            'matched_user' => $user instanceof User,
            'rate_limited' => false,
            'source' => $source,
            'token_id' => isset($magicToken) ? $magicToken->getKey() : null,
        ]);

        return self::RESPONSE_MESSAGE;
    }

    public function rateLimitKey(string $email): string
    {
        return 'magic-login-request:email:'.hash('sha256', Str::lower(trim($email)));
    }
}
