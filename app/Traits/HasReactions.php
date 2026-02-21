<?php

namespace App\Traits;

use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait HasReactions
{
    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    public function reactionFrom(User $user): ?Reaction
    {
        return $this->reactions()
            ->where('user_id', $user->getKey())
            ->first();
    }

    public function hasReactionFrom(User $user, ?string $type = null): bool
    {
        $query = $this->reactions()->where('user_id', $user->getKey());

        if ($type !== null) {
            $query->where('type', $type);
        }

        return $query->exists();
    }

    public function reactBy(User $user, string $type = 'like'): Reaction
    {
        return DB::transaction(function () use ($user, $type): Reaction {
            $existing = $this->reactions()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->type !== $type) {
                    $existing->forceFill(['type' => $type])->save();
                }

                return $existing;
            }

            $reaction = $this->reactions()->create([
                'user_id' => $user->getKey(),
                'type' => $type,
            ]);

            if (method_exists($this, 'incrementCounter')) {
                $this->incrementCounter('reactions_count');
            }

            return $reaction;
        });
    }

    public function removeReactionBy(User $user): bool
    {
        $reaction = $this->reactionFrom($user);

        if (! $reaction) {
            return false;
        }

        $deleted = (bool) $reaction->delete();

        if ($deleted && method_exists($this, 'decrementCounter')) {
            $this->decrementCounter('reactions_count');
        }

        return $deleted;
    }

    public function toggleReaction(User $user, string $type = 'like'): ?Reaction
    {
        $existing = $this->reactionFrom($user);

        if ($existing && $existing->type === $type) {
            $this->removeReactionBy($user);

            return null;
        }

        return $this->reactBy($user, $type);
    }

    public function reactionCounts(): Collection
    {
        return $this->reactions()
            ->select('type', DB::raw('COUNT(*) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');
    }

    public function scopeWithReactionsCount(Builder $query): Builder
    {
        return $query->withCount('reactions');
    }
}
