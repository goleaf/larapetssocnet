<?php

namespace App\Services;

use App\Models\User;
use App\Models\UsernameRedirect;
use App\Support\Usernames\UsernameNormalizer;

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
            return [
                'user' => $user,
                'redirect' => null,
                'normalized' => $normalized,
            ];
        }

        $redirect = UsernameRedirect::query()
            ->active()
            ->where('old_username', $normalized)
            ->with('user')
            ->first();

        if (! $redirect?->user) {
            return null;
        }

        return [
            'user' => $redirect->user,
            'redirect' => $redirect,
            'normalized' => $normalized,
        ];
    }
}
