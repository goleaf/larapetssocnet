<?php

namespace App\Services;

use App\Enums\PostStatus;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class SyncGroupCountersService
{
    public function syncMembersCount(Group $group): void
    {
        if (! $this->hasColumn('groups', 'members_count')) {
            return;
        }

        $count = GroupMember::query()
            ->forGroup((int) $group->getKey())
            ->active()
            ->count();

        $group->updateQuietly([
            'members_count' => $count,
        ]);
    }

    public function syncPostsCount(Group $group): void
    {
        if (! $this->hasColumn('groups', 'posts_count')) {
            return;
        }

        $postIdsQuery = function (Builder $query) use ($group): void {
            $query->select('post_id')
                ->from('group_posts')
                ->where('group_id', $group->getKey());
        };

        $count = Post::query()
            ->where('status', PostStatus::Published->value)
            ->where(function (Builder $publishedQuery): void {
                $publishedQuery
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->whereNull('deleted_at')
            ->where(function (Builder $groupQuery) use ($group, $postIdsQuery): void {
                $groupQuery
                    ->where('group_id', $group->getKey())
                    ->orWhereIn('id', $postIdsQuery);
            })
            ->distinct('posts.id')
            ->count('posts.id');

        $group->updateQuietly([
            'posts_count' => $count,
        ]);
    }

    public function syncAll(Group $group): void
    {
        $this->syncMembersCount($group);
        $this->syncPostsCount($group);
    }

    public function rebuildAll(int $chunkSize = 100): void
    {
        Group::query()
            ->select(['id'])
            ->chunkById($chunkSize, function ($groups): void {
                foreach ($groups as $group) {
                    $this->syncAll($group);
                }
            });
    }

    private function hasColumn(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }
}
