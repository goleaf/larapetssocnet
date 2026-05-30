<?php

namespace App\Traits;

use App\Models\Content\Reaction;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            $query->where('type', Reaction::normalizeType($type));
        }

        return $query->exists();
    }

    public function reactBy(User $user, string $type = Reaction::TYPE_PAW): Reaction
    {
        return DB::transaction(function () use ($user, $type): Reaction {
            $type = Reaction::normalizeType($type);
            $existing = $this->reactions()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if (Reaction::normalizeType((string) $existing->type) !== $type) {
                    $this->decrementReactionTypeCounter((string) $existing->type);
                    $existing->forceFill(['type' => $type])->save();
                    $this->incrementReactionTypeCounter($type);
                }

                return $existing;
            }

            $reaction = $this->reactions()->create([
                'user_id' => $user->getKey(),
                'type' => $type,
            ]);

            if (method_exists($this, 'incrementCounter')) {
                $this->incrementCounter('reactions_count');
                $this->incrementReactionTypeCounter($type);
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
            $this->decrementReactionTypeCounter((string) $reaction->type);
        }

        return $deleted;
    }

    public function toggleReaction(User $user, string $type = Reaction::TYPE_PAW): ?Reaction
    {
        $type = Reaction::normalizeType($type);
        $existing = $this->reactionFrom($user);

        if ($existing && Reaction::normalizeType((string) $existing->type) === $type) {
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

    private function incrementReactionTypeCounter(string $type): void
    {
        $column = Reaction::counterColumn($type);

        if ($this->hasReactionTypeCounter($column)) {
            $this->incrementCounter($column);
        }
    }

    private function decrementReactionTypeCounter(string $type): void
    {
        $column = Reaction::counterColumn($type);

        if ($this->hasReactionTypeCounter($column)) {
            $this->decrementCounter($column);
        }
    }

    private function hasReactionTypeCounter(string $column): bool
    {
        static $columnsByTable = [];

        $table = $this->getTable();

        if (! array_key_exists($table, $columnsByTable)) {
            $columnsByTable[$table] = Schema::getColumnListing($table);
        }

        return in_array($column, $columnsByTable[$table], true);
    }
}
