<?php

namespace App\Models;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class GroupMember extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'group_id',
        'user_id',
        'role',
        'status',
        'joined_at',
        'invited_by',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopeRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    public function scopeForGroup(Builder $query, int $groupId): Builder
    {
        return $query->where('group_id', $groupId);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeManagers(Builder $query): Builder
    {
        return $query->whereIn('role', ['owner', 'admin']);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $statusQuery): void {
            $statusQuery
                ->whereNull('status')
                ->orWhereIn('status', ['active', 'accepted']);
        });
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * @param  Collection<int, int>  $groupIds
     * @return Collection<int, self>
     */
    public static function membershipMapForUserAndGroups(int $userId, Collection $groupIds): Collection
    {
        if ($groupIds->isEmpty()) {
            return collect();
        }

        return self::query()
            ->forUser($userId)
            ->whereIn('group_id', $groupIds->all())
            ->get(['id', 'group_id', 'status', 'role', 'updated_at'])
            ->keyBy('group_id');
    }

    public static function firstForGroupAndUser(int $groupId, int $userId): ?self
    {
        return self::query()
            ->forGroup($groupId)
            ->forUser($userId)
            ->first();
    }

    public static function paginateActiveForGroup(Group $group, int $perPage = 20, string $pageName = 'members_page'): LengthAwarePaginator
    {
        return self::query()
            ->forGroup((int) $group->getKey())
            ->active()
            ->with('user:id,name,username')
            ->orderByDesc('role')
            ->orderBy('joined_at')
            ->paginate($perPage, ['*'], $pageName)
            ->withQueryString();
    }

    /**
     * @return Collection<int, self>
     */
    public static function pendingForGroup(Group $group): Collection
    {
        return self::query()
            ->forGroup((int) $group->getKey())
            ->pending()
            ->with('user:id,name,username')
            ->latest('created_at')
            ->get();
    }
}
