<?php

namespace App\Services;

use App\Models\Identity\User;
use App\Models\Identity\UsernameRedirect;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameRules;

class UsernameRedirectResolver
{
    /**
     * @return array{user: User, redirect: ?UsernameRedirect, normalized: string}|null
     */
    public function resolve(string $rawUsername): ?array
    {
        $normalized = UsernameNormalizer::normalize($rawUsername);

        if ($normalized === '') {
            return null;
        }

        $user = User::query()
            ->where('username', $normalized)
            ->first();

        if ($user) {
            if ($user->isUnavailableForProfile()) {
                return null;
            }

            return [
                'user' => $user,
                'redirect' => null,
                'normalized' => $normalized,
            ];
        }

        if (
            in_array($normalized, UsernameRules::reservedList(), true)
            || in_array($normalized, UsernameRules::routeReservedList(), true)
        ) {
            return null;
        }

        $redirect = UsernameRedirect::query()
            ->active()
            ->where('old_username', $normalized)
            ->with('user')
            ->first();

        $redirectUser = $redirect?->user;

        if (! $redirectUser instanceof User || $redirectUser->isUnavailableForProfile()) {
            return null;
        }

        return [
            'user' => $redirectUser,
            'redirect' => $redirect,
            'normalized' => $normalized,
        ];
    }
}
