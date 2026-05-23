<?php

namespace App\Services\Auth;

use App\Models\Identity\User;
use App\Models\Security\MagicLoginToken;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MagicLinkService
{
    public const int EXPIRY_MINUTES = 15;

    /**
     * @return array{0: MagicLoginToken, 1: string}
     */
    public function create(User $user): array
    {
        $expiresAt = now()->addMinutes(self::EXPIRY_MINUTES);

        return DB::transaction(function () use ($user, $expiresAt): array {
            MagicLoginToken::query()
                ->where('user_id', $user->getKey())
                ->whereNull('used_at')
                ->delete();

            $plainToken = Str::random(64);
            $magicToken = MagicLoginToken::query()->create([
                'user_id' => $user->getKey(),
                'token' => Str::random(64),
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => $expiresAt,
            ]);

            return [$magicToken, $plainToken];
        });
    }

    public function consume(string $plainToken): MagicLinkConsumptionResult
    {
        $plainToken = trim($plainToken);

        if ($plainToken === '') {
            return MagicLinkConsumptionResult::invalid();
        }

        $tokenHash = hash('sha256', $plainToken);
        $now = now();

        $magicToken = MagicLoginToken::query()
            ->with('user')
            ->where('token_hash', $tokenHash)
            ->first();

        if (! $magicToken instanceof MagicLoginToken) {
            return MagicLinkConsumptionResult::invalid();
        }

        if ($magicToken->used_at !== null) {
            return MagicLinkConsumptionResult::used($magicToken);
        }

        if ($this->hasExpired($magicToken, $now)) {
            return MagicLinkConsumptionResult::expired($magicToken);
        }

        $updated = MagicLoginToken::query()
            ->whereKey($magicToken->getKey())
            ->whereNull('used_at')
            ->where('expires_at', '>', $now)
            ->update(['used_at' => $now]);

        if ($updated === 1) {
            return MagicLinkConsumptionResult::consumed($magicToken->refresh()->load('user'));
        }

        $magicToken->refresh()->load('user');

        if ($magicToken->used_at !== null) {
            return MagicLinkConsumptionResult::used($magicToken);
        }

        if ($this->hasExpired($magicToken, now())) {
            return MagicLinkConsumptionResult::expired($magicToken);
        }

        return MagicLinkConsumptionResult::invalid();
    }

    private function hasExpired(MagicLoginToken $magicToken, CarbonInterface $now): bool
    {
        $expiresAt = $magicToken->getAttribute('expires_at');

        if (! $expiresAt instanceof CarbonInterface) {
            return true;
        }

        return $expiresAt->lessThanOrEqualTo($now);
    }
}
