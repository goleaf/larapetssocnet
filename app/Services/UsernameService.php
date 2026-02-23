<?php

namespace App\Services;

use App\Exceptions\UsernameChangeCooldownException;
use App\Exceptions\UsernameNotAvailableException;
use App\Exceptions\UsernameReservedException;
use App\Models\ReservedUsername;
use App\Models\User;
use App\Models\UsernameRedirect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UsernameService
{
    public function generate(string $name): string
    {
        $base = (string) Str::of($name)
            ->lower()
            ->replaceMatches('/[^a-z0-9_]+/', '_')
            ->trim('_')
            ->limit(25, '');

        if (strlen($base) < 3) {
            $base = 'user_'.random_int(100, 999);
        }

        for ($i = 0; $i < 10; $i++) {
            $candidate = $i === 0 ? $base : Str::limit($base, 25, '').random_int(100, 999);

            if ($this->isAvailable($candidate)) {
                return strtolower($candidate);
            }
        }

        do {
            $fallback = 'user_'.Str::lower(Str::random(8));
        } while (! $this->isAvailable($fallback));

        return $fallback;
    }

    public function isAvailable(string $username, ?int $excludeUserId = null): bool
    {
        $normalized = strtolower(trim($username));

        if (! preg_match('/^[a-zA-Z0-9_]{3,30}$/', $normalized)) {
            return false;
        }

        if (ReservedUsername::isReserved($normalized)) {
            return false;
        }

        return ! User::query()
            ->where('username', $normalized)
            ->when($excludeUserId, fn ($query, $id) => $query->where('id', '!=', $id))
            ->exists();
    }

    public function change(User $user, string $newUsername, bool $ignoreCooldown = false): bool
    {
        $normalized = strtolower(trim($newUsername));

        if (ReservedUsername::isReserved($normalized)) {
            throw new UsernameReservedException;
        }

        if (! $ignoreCooldown && ! $user->canChangeUsername()) {
            throw new UsernameChangeCooldownException($user->daysUntilUsernameChange());
        }

        if (! $this->isAvailable($normalized, $user->id)) {
            throw new UsernameNotAvailableException;
        }

        DB::transaction(function () use ($user, $normalized): void {
            if ($user->username !== null && $user->username !== '') {
                UsernameRedirect::query()->create([
                    'old_username' => $user->username,
                    'user_id' => $user->id,
                    'redirects_until' => now()->addDays(90),
                    'created_at' => now(),
                ]);
            }

            UsernameRedirect::query()
                ->where('old_username', $normalized)
                ->where('user_id', $user->id)
                ->delete();

            $user->update([
                'username' => $normalized,
                'username_changed_at' => now(),
            ]);
        });

        return true;
    }
}
