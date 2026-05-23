<?php

namespace App\Services\Auth;

use App\Models\Security\MagicLoginToken;

class MagicLinkConsumptionResult
{
    public const string CONSUMED = 'consumed';

    public const string INVALID = 'invalid';

    public const string EXPIRED = 'expired';

    public const string USED = 'used';

    private function __construct(
        public readonly string $status,
        public readonly ?MagicLoginToken $token = null,
    ) {}

    public static function consumed(MagicLoginToken $token): self
    {
        return new self(self::CONSUMED, $token);
    }

    public static function invalid(): self
    {
        return new self(self::INVALID);
    }

    public static function expired(MagicLoginToken $token): self
    {
        return new self(self::EXPIRED, $token);
    }

    public static function used(MagicLoginToken $token): self
    {
        return new self(self::USED, $token);
    }

    public function successful(): bool
    {
        return $this->status === self::CONSUMED && $this->token instanceof MagicLoginToken;
    }
}
