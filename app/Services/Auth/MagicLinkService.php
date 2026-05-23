<?php

namespace App\Services\Auth;

use App\Models\Identity\User;
use App\Models\Security\MagicLoginToken;
use App\Notifications\MagicLoginLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class MagicLinkService
{
    public const int EXPIRY_MINUTES = 15;

    public function createAndSend(User $user, Request $request): MagicLoginToken
    {
        $expiresAt = now()->addMinutes(self::EXPIRY_MINUTES);

        [$magicToken, $plainToken] = DB::transaction(function () use ($user, $expiresAt): array {
            MagicLoginToken::query()
                ->where('user_id', $user->getKey())
                ->whereNull('used_at')
                ->delete();

            $plainToken = Str::random(64);
            $magicToken = MagicLoginToken::query()->create([
                'user_id' => $user->getKey(),
                'token' => Str::random(40),
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => $expiresAt,
            ]);

            return [$magicToken, $plainToken];
        });

        $url = URL::temporarySignedRoute(
            'magic-login.consume',
            $expiresAt,
            [
                'token' => $magicToken->token,
                'secret' => $plainToken,
            ]
        );

        $user->notify(new MagicLoginLink($url));

        return $magicToken;
    }

    public function consume(string $publicToken, string $plainToken): ?MagicLoginToken
    {
        $tokenHash = hash('sha256', $plainToken);

        $magicToken = MagicLoginToken::query()
            ->with('user')
            ->where('token_hash', $tokenHash)
            ->where('token', $publicToken)
            ->first();

        if (! $magicToken instanceof MagicLoginToken || ! $magicToken->isConsumable()) {
            return null;
        }

        if (! hash_equals((string) $magicToken->token_hash, $tokenHash)) {
            return null;
        }

        $updated = MagicLoginToken::query()
            ->whereKey($magicToken->getKey())
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        return $updated === 1 ? $magicToken->refresh() : null;
    }
}
