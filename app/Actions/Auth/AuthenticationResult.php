<?php

namespace App\Actions\Auth;

use App\Models\Identity\User;

class AuthenticationResult
{
    private function __construct(
        public readonly bool $successful,
        public readonly ?User $user,
        public readonly ?string $failureReason = null,
        public readonly ?string $message = null,
        public readonly ?int $lockoutSeconds = null,
        public readonly ?string $redirectRoute = null,
        public readonly bool $requiresTwoFactor = false,
    ) {}

    public static function success(User $user, bool $requiresTwoFactor = false): self
    {
        return new self(
            successful: true,
            user: $user,
            requiresTwoFactor: $requiresTwoFactor,
        );
    }

    public static function restricted(User $user, string $reason, string $redirectRoute): self
    {
        return new self(
            successful: true,
            user: $user,
            failureReason: $reason,
            redirectRoute: $redirectRoute,
        );
    }

    public static function failure(string $reason, string $message): self
    {
        return new self(
            successful: false,
            user: null,
            failureReason: $reason,
            message: $message,
        );
    }

    public static function lockedOut(int $seconds): self
    {
        return new self(
            successful: false,
            user: null,
            failureReason: 'locked_out',
            message: sprintf(
                'Too many failed login attempts. Please wait %s before trying again.',
                self::formatMinutes($seconds),
            ),
            lockoutSeconds: $seconds,
        );
    }

    public function failed(): bool
    {
        return ! $this->successful;
    }

    private static function formatMinutes(int $seconds): string
    {
        $minutes = max(1, (int) ceil($seconds / 60));

        return $minutes === 1 ? '1 minute' : $minutes.' minutes';
    }
}
