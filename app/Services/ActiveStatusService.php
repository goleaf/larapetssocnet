<?php

namespace App\Services;

use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ActiveStatusService
{
    private static ?bool $hasLastSeenColumn = null;

    public function touch(User $user, ?CarbonInterface $now = null): bool
    {
        if (! $user->getKey() || ! $this->hasLastSeenColumn()) {
            return false;
        }

        $requestKey = 'active-status.last-seen-touched.'.$user->getKey();

        if (request()->attributes->get($requestKey) === true) {
            return false;
        }

        $currentTime = $now instanceof CarbonInterface
            ? CarbonImmutable::instance($now)
            : CarbonImmutable::instance(now());

        try {
            $storedLastSeen = User::query()
                ->whereKey($user->getKey())
                ->value('last_seen_at');

            if (
                $storedLastSeen !== null
                && CarbonImmutable::parse($storedLastSeen)->greaterThanOrEqualTo(
                    $currentTime->subSeconds(User::ACTIVE_STATUS_WRITE_THROTTLE_SECONDS)
                )
            ) {
                request()->attributes->set($requestKey, true);

                return false;
            }

            User::withoutTimestamps(fn (): int => User::query()->upsert(
                [
                    [
                        'id' => $user->getKey(),
                        'name' => $user->name,
                        'email' => $user->email,
                        'password' => $user->password,
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                        'last_seen_at' => $currentTime,
                    ],
                ],
                uniqueBy: ['id'],
                update: ['last_seen_at']
            ));

            $user->forceFill([
                'last_seen_at' => $currentTime,
            ]);

            request()->attributes->set($requestKey, true);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function hasLastSeenColumn(): bool
    {
        return self::$hasLastSeenColumn ??= Schema::hasColumn('users', 'last_seen_at');
    }
}
