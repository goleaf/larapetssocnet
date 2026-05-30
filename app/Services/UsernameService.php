<?php

namespace App\Services;

use App\Exceptions\UsernameChangeCooldownException;
use App\Exceptions\UsernameNotAvailableException;
use App\Exceptions\UsernameReservedException;
use App\Models\Identity\ReservedUsername;
use App\Models\Identity\User;
use App\Models\Identity\UsernameRedirect;
use App\Notifications\Database\Account\UsernameChanged;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UsernameService
{
    public function generate(string $name): string
    {
        $base = UsernameNormalizer::generateBase($name);

        $minLength = UsernameRules::minLength();
        $maxLength = UsernameRules::maxLength();

        if (strlen($base) < $minLength) {
            $base = 'user_'.random_int(100, 999);
        }

        $base = (string) Str::of($base)->limit($maxLength, '');

        for ($i = 0; $i < 10; $i++) {
            $candidate = $i === 0 ? $base : Str::of($base)->limit($maxLength - 3, '').random_int(100, 999);

            if ($this->isAvailable($candidate)) {
                return UsernameNormalizer::normalize($candidate);
            }
        }

        do {
            $fallback = 'user_'.Str::lower(Str::random(8));
        } while (! $this->isAvailable($fallback));

        return $fallback;
    }

    public function isAvailable(string $username, ?int $excludeUserId = null): bool
    {
        return UsernameRules::isAvailable($username, $excludeUserId);
    }

    public function change(
        User $user,
        string $newUsername,
        ?User $actor = null,
        ?string $reason = null,
        bool $ignoreCooldown = false
    ): bool {
        $normalized = UsernameNormalizer::normalize($newUsername);

        if ($normalized === UsernameNormalizer::normalize($user->username)) {
            return false;
        }

        if (UsernameRules::isReserved($normalized)) {
            throw new UsernameReservedException;
        }

        if (! $ignoreCooldown && ! $user->canChangeUsername()) {
            throw new UsernameChangeCooldownException($user->daysUntilUsernameChange());
        }

        if (! $this->isAvailable($normalized, $user->id)) {
            throw new UsernameNotAvailableException;
        }

        $oldUsername = (string) $user->username;

        DB::transaction(function () use ($user, $normalized, $oldUsername, $actor, $reason): void {
            if ($oldUsername !== '') {
                UsernameRedirect::query()->create([
                    'old_username' => $oldUsername,
                    'user_id' => $user->id,
                    'redirects_until' => now()->addDays((int) config('usernames.redirect_ttl_days', 36500)),
                    'created_at' => now(),
                ]);
            }

            UsernameRedirect::query()
                ->where('old_username', $normalized)
                ->where('user_id', $user->id)
                ->delete();

            $user->update([
                'username' => $normalized,
                'username_change_allowed_at' => now(),
            ]);

            if ($oldUsername !== '' && (bool) config('usernames.reserve_old_usernames', true)) {
                ReservedUsername::query()->firstOrCreate(
                    ['username' => $oldUsername],
                    ['reason' => 'previous_username']
                );
            }

            app(UsernameChangeService::class)->record(
                $user,
                $oldUsername,
                $normalized,
                $actor ?? $user,
                $reason,
                request()?->ip(),
                request()?->userAgent()
            );
        });

        if ($user->notificationEnabled('username_changed')) {
            $user->notify(new UsernameChanged($oldUsername, $normalized));
        }

        return true;
    }
}
