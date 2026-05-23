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

        [$magicToken, $plainToken] = DB::transaction(function () use ($user, $request, $expiresAt): array {
            MagicLoginToken::query()
                ->where('user_id', $user->getKey())
                ->whereNull('consumed_at')
                ->delete();

            $plainToken = Str::random(64);
            $magicToken = MagicLoginToken::query()->create([
                'public_id' => (string) Str::uuid(),
                'user_id' => $user->getKey(),
                'token_hash' => hash('sha256', $plainToken),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'expires_at' => $expiresAt,
            ]);

            return [$magicToken, $plainToken];
        });

        $url = URL::temporarySignedRoute(
            'magic-login.consume',
            $expiresAt,
            [
                'token' => $magicToken->public_id,
                'secret' => $plainToken,
            ]
        );

        $user->notify(new MagicLoginLink($url));

        return $magicToken;
    }

    public function consume(string $publicId, string $plainToken): ?MagicLoginToken
    {
        $magicToken = MagicLoginToken::query()
            ->with('user')
            ->where('public_id', $publicId)
            ->first();

        if (! $magicToken instanceof MagicLoginToken || ! $magicToken->isConsumable()) {
            return null;
        }

        if (! hash_equals((string) $magicToken->token_hash, hash('sha256', $plainToken))) {
            return null;
        }

        $updated = MagicLoginToken::query()
            ->whereKey($magicToken->getKey())
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        return $updated === 1 ? $magicToken->refresh() : null;
    }
}
