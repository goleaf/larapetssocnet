<?php

namespace App\Services\Maintenance;

use App\Actions\Hashtags\RecalculateHashtagUsageCountsAction;
use App\Actions\Hashtags\SyncPostHashtagsAction;
use App\Enums\GroupMemberRole;
use App\Enums\GroupMemberStatus;
use App\Enums\PostStatus;
use App\Models\Comment;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Hashtag;
use App\Models\Like;
use App\Models\Notification;
use App\Models\Post;
use App\Models\PostReaction;
use App\Models\Reaction;
use App\Models\User;
use App\Services\ContentService;
use App\Services\PostService;
use App\Services\SyncGroupCountersService;
use App\Services\SyncPostCountersService;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameRules;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MaintenanceTaskService
{
    /**
     * @var list<string>
     */
    public const REALTIME_TASKS = [
        'publish-scheduled-posts',
        'prune-deleted-accounts',
        'prune-old-notifications',
    ];

    public function __construct(
        private readonly SyncPostHashtagsAction $syncHashtags,
        private readonly RecalculateHashtagUsageCountsAction $recalculateHashtags,
        private readonly ContentService $content,
        private readonly PostService $posts,
        private readonly SyncPostCountersService $postCounters,
        private readonly SyncGroupCountersService $groupCounters,
        private readonly BladeTagMaintenanceService $bladeTags,
        private readonly QueuePauseService $queues,
    ) {}

    /**
     * @return array<string, array{label: string, description: string, realtime: bool}>
     */
    public function tasks(): array
    {
        return [
            'publish-scheduled-posts' => [
                'label' => 'Publish scheduled posts',
                'description' => 'Publishes scheduled posts whose publish time has arrived.',
                'realtime' => true,
            ],
            'prune-deleted-accounts' => [
                'label' => 'Prune deleted accounts',
                'description' => 'Permanently deletes accounts after their grace period.',
                'realtime' => true,
            ],
            'prune-old-notifications' => [
                'label' => 'Prune old notifications',
                'description' => 'Deletes notifications older than the configured retention window.',
                'realtime' => true,
            ],
            'backfill-post-hashtags' => [
                'label' => 'Backfill post hashtags',
                'description' => 'Syncs hashtag relations for existing posts.',
                'realtime' => false,
            ],
            'recount-hashtag-usage' => [
                'label' => 'Recount hashtag usage',
                'description' => 'Recalculates published post counts for hashtags.',
                'realtime' => false,
            ],
            'backfill-usernames' => [
                'label' => 'Backfill usernames',
                'description' => 'Normalizes missing or invalid usernames.',
                'realtime' => false,
            ],
            'rebuild-comment-counters' => [
                'label' => 'Rebuild comment counters',
                'description' => 'Rebuilds post comment counts, reply counts, and missing comment HTML.',
                'realtime' => false,
            ],
            'rebuild-engagement-counters' => [
                'label' => 'Rebuild engagement counters',
                'description' => 'Rebuilds reaction, like, save, share, and comment counters.',
                'realtime' => false,
            ],
            'rebuild-group-counters' => [
                'label' => 'Rebuild group counters',
                'description' => 'Rebuilds group member and post counters.',
                'realtime' => false,
            ],
            'rebuild-group-memberships' => [
                'label' => 'Rebuild group memberships',
                'description' => 'Ensures owner memberships exist and active member counters are correct.',
                'realtime' => false,
            ],
            'fix-blade-tags' => [
                'label' => 'Fix Blade tags',
                'description' => 'Normalizes malformed Blade attribute spacing and optional dark utilities.',
                'realtime' => false,
            ],
            'pause-queue-for' => [
                'label' => 'Pause queue temporarily',
                'description' => 'Pauses a queue for a limited number of seconds.',
                'realtime' => false,
            ],
            'badge-award' => [
                'label' => 'Award badges',
                'description' => 'Placeholder migrated from the previous command; no badge award rules exist yet.',
                'realtime' => false,
            ],
            'badges-recalculate' => [
                'label' => 'Recalculate badges',
                'description' => 'Placeholder migrated from the previous command; no badge recalculation rules exist yet.',
                'realtime' => false,
            ],
            'recalculate-unread-counts' => [
                'label' => 'Recalculate unread counts',
                'description' => 'Placeholder migrated from the previous command; unread counts are computed from messages.',
                'realtime' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function run(string $task, array $options = []): MaintenanceTaskResult
    {
        return match ($task) {
            'publish-scheduled-posts' => $this->publishScheduledPosts(),
            'prune-deleted-accounts' => $this->pruneDeletedAccounts(),
            'prune-old-notifications' => $this->pruneOldNotifications((int) ($options['days'] ?? 90)),
            'backfill-post-hashtags' => $this->backfillPostHashtags(
                (int) ($options['chunk'] ?? 200),
                (bool) ($options['recount'] ?? false),
            ),
            'recount-hashtag-usage' => $this->recountHashtagUsage(),
            'backfill-usernames' => $this->backfillUsernames(),
            'rebuild-comment-counters' => $this->rebuildCommentCounters(),
            'rebuild-engagement-counters' => $this->rebuildEngagementCounters((bool) ($options['import_legacy'] ?? false)),
            'rebuild-group-counters' => $this->rebuildGroupCounters((int) ($options['chunk'] ?? 100)),
            'rebuild-group-memberships' => $this->rebuildGroupMemberships((int) ($options['chunk'] ?? 100)),
            'fix-blade-tags' => $this->bladeTags->fix(
                $this->pathsFromOptions($options),
                (bool) ($options['remove_dark'] ?? false),
                (bool) ($options['dry_run'] ?? false),
            ),
            'pause-queue-for' => $this->queues->pauseFor(
                (string) ($options['queue'] ?? 'database:default'),
                (int) ($options['seconds'] ?? 60),
            ),
            'badge-award' => MaintenanceTaskResult::make($task, 'No badge award rules exist yet.', ['processed' => 0]),
            'badges-recalculate' => MaintenanceTaskResult::make($task, 'No badge recalculation rules exist yet.', ['processed' => 0]),
            'recalculate-unread-counts' => MaintenanceTaskResult::make($task, 'Unread counts are computed from unread messages.', ['processed' => 0]),
            default => throw new InvalidArgumentException("Unknown maintenance task [{$task}]."),
        };
    }

    /**
     * @return array<int, MaintenanceTaskResult>
     */
    public function runRealtimeDueTasks(): array
    {
        $results = [];

        foreach (self::REALTIME_TASKS as $task) {
            $cacheKey = "maintenance:realtime:{$task}";
            $ttl = $task === 'publish-scheduled-posts' ? now()->addMinute() : now()->addHour();

            if (! Cache::add($cacheKey, true, $ttl)) {
                continue;
            }

            $results[] = $this->run($task);
        }

        return $results;
    }

    public function publishScheduledPosts(): MaintenanceTaskResult
    {
        $published = 0;
        $now = now();

        Post::query()
            ->scheduled()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use (&$published): void {
                foreach ($chunk as $post) {
                    $this->posts->publish($post, $post->getAttribute('published_at'));
                    $published++;
                }
            });

        return MaintenanceTaskResult::make('publish-scheduled-posts', "Published {$published} scheduled post(s).", [
            'published' => $published,
        ]);
    }

    public function pruneDeletedAccounts(): MaintenanceTaskResult
    {
        $pruned = 0;

        User::query()
            ->whereNotNull('scheduled_deletion_at')
            ->where('scheduled_deletion_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$pruned): void {
                foreach ($users as $user) {
                    $user->clearMediaCollection('avatar');
                    $user->clearMediaCollection('cover');
                    $user->clearMediaCollection('photos');
                    $user->forceDelete();
                    $pruned++;
                }
            });

        return MaintenanceTaskResult::make('prune-deleted-accounts', "Pruned {$pruned} deleted account(s).", [
            'pruned' => $pruned,
        ]);
    }

    public function pruneOldNotifications(int $days = 90): MaintenanceTaskResult
    {
        $days = max(1, $days);
        $deleted = Notification::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        return MaintenanceTaskResult::make('prune-old-notifications', "Deleted {$deleted} old notification(s).", [
            'deleted' => (int) $deleted,
            'days' => $days,
        ]);
    }

    public function backfillPostHashtags(int $chunkSize = 200, bool $recount = false): MaintenanceTaskResult
    {
        $chunkSize = max(50, $chunkSize);
        $processed = 0;

        Post::query()
            ->select(['posts.id', 'posts.body', 'posts.status', 'posts.published_at'])
            ->chunkById($chunkSize, function ($posts) use (&$processed): void {
                foreach ($posts as $post) {
                    $this->syncHashtags->handle($post, false);
                    $processed++;
                }
            });

        if ($recount) {
            $this->recalculateHashtags->handle();
        }

        return MaintenanceTaskResult::make('backfill-post-hashtags', "Backfilled hashtags for {$processed} post(s).", [
            'processed' => $processed,
            'recounted' => $recount,
        ]);
    }

    public function recountHashtagUsage(): MaintenanceTaskResult
    {
        $this->recalculateHashtags->handle();
        $hashtags = Hashtag::query()->count();

        return MaintenanceTaskResult::make('recount-hashtag-usage', 'Hashtag usage counts recalculated.', [
            'hashtags' => $hashtags,
        ]);
    }

    public function backfillUsernames(): MaintenanceTaskResult
    {
        $processed = 0;
        $updated = 0;

        User::query()
            ->select(['id', 'name', 'display_name', 'email', 'username'])
            ->orderBy('id')
            ->chunkById(200, function ($users) use (&$processed, &$updated): void {
                foreach ($users as $user) {
                    $processed++;
                    $current = (string) $user->getAttribute('username');
                    $normalized = UsernameNormalizer::normalize($current);

                    if ($normalized === '') {
                        $seed = (string) ($user->getAttribute('display_name')
                            ?: $user->getAttribute('name')
                            ?: Str::before((string) $user->getAttribute('email'), '@'));
                        $normalized = UsernameNormalizer::generateBase($seed);
                    }

                    if ($normalized === '') {
                        $normalized = 'petlover';
                    }

                    $candidate = $normalized;

                    if (! UsernameRules::isAvailable($candidate, (int) $user->getKey())) {
                        $candidate = User::generateUniqueUsername($candidate);
                    }

                    if ($candidate !== $current) {
                        $user->updateQuietly(['username' => $candidate]);
                        $updated++;
                    }
                }
            });

        return MaintenanceTaskResult::make('backfill-usernames', "Processed {$processed} user(s), updated {$updated}.", [
            'processed' => $processed,
            'updated' => $updated,
        ]);
    }

    public function rebuildCommentCounters(): MaintenanceTaskResult
    {
        $postsUpdated = 0;
        $commentsUpdated = 0;
        $htmlUpdated = 0;

        Post::query()
            ->select(['id'])
            ->withCount(['comments as computed_comments'])
            ->chunkById(200, function ($posts) use (&$postsUpdated): void {
                foreach ($posts as $post) {
                    $post->updateQuietly([
                        'comments_count' => (int) $post->getAttribute('computed_comments'),
                    ]);
                    $postsUpdated++;
                }
            });

        Comment::query()
            ->select(['id'])
            ->withCount(['replies as computed_replies'])
            ->chunkById(200, function ($comments) use (&$commentsUpdated): void {
                foreach ($comments as $comment) {
                    $comment->updateQuietly([
                        'replies_count' => (int) $comment->getAttribute('computed_replies'),
                    ]);
                    $commentsUpdated++;
                }
            });

        Comment::query()
            ->select(['id', 'body'])
            ->whereNull('body_html')
            ->chunkById(200, function ($comments) use (&$htmlUpdated): void {
                foreach ($comments as $comment) {
                    $comment->updateQuietly([
                        'body_html' => $this->content->process((string) $comment->getAttribute('body')),
                    ]);
                    $htmlUpdated++;
                }
            });

        return MaintenanceTaskResult::make('rebuild-comment-counters', 'Comment counters rebuilt.', [
            'posts_updated' => $postsUpdated,
            'comments_updated' => $commentsUpdated,
            'html_updated' => $htmlUpdated,
        ]);
    }

    public function rebuildEngagementCounters(bool $importLegacy = false): MaintenanceTaskResult
    {
        if ($importLegacy) {
            $this->importLegacyReactions();
        }

        $processed = 0;

        Post::query()
            ->select(['posts.id'])
            ->chunkById(300, function ($posts) use (&$processed): void {
                foreach ($posts as $post) {
                    $this->postCounters->sync($post);
                    $processed++;
                }
            });

        return MaintenanceTaskResult::make('rebuild-engagement-counters', "Rebuilt engagement counters for {$processed} post(s).", [
            'processed' => $processed,
            'import_legacy' => $importLegacy,
        ]);
    }

    public function rebuildGroupCounters(int $chunkSize = 100): MaintenanceTaskResult
    {
        $chunkSize = max(1, $chunkSize);
        $this->groupCounters->rebuildAll($chunkSize);

        return MaintenanceTaskResult::make('rebuild-group-counters', 'Group counters rebuilt.', [
            'chunk' => $chunkSize,
        ]);
    }

    public function rebuildGroupMemberships(int $chunkSize = 100): MaintenanceTaskResult
    {
        $chunkSize = max(1, $chunkSize);
        $processed = 0;
        $ownersAligned = 0;

        Group::query()
            ->select(['id', 'owner_id', 'owner_user_id'])
            ->chunkById($chunkSize, function ($groups) use (&$processed, &$ownersAligned): void {
                foreach ($groups as $group) {
                    $processed++;
                    $ownerId = (int) ($group->getAttribute('owner_user_id') ?? $group->getAttribute('owner_id') ?? 0);

                    if ($ownerId > 0 && $this->alignOwnerMembership($group, $ownerId)) {
                        $ownersAligned++;
                    }

                    $this->groupCounters->syncMembersCount($group);
                }
            });

        return MaintenanceTaskResult::make('rebuild-group-memberships', "Rebuilt memberships for {$processed} group(s).", [
            'processed' => $processed,
            'owners_aligned' => $ownersAligned,
            'chunk' => $chunkSize,
        ]);
    }

    private function importLegacyReactions(): void
    {
        $hasLikes = Schema::hasTable('likes');
        $hasPostReactions = Schema::hasTable('post_reactions');

        if (! $hasLikes && ! $hasPostReactions) {
            return;
        }

        Post::query()
            ->select(['posts.id'])
            ->chunkById(300, function ($posts) use ($hasLikes, $hasPostReactions): void {
                foreach ($posts as $post) {
                    if ($hasPostReactions) {
                        PostReaction::query()
                            ->where('post_id', $post->getKey())
                            ->get(['user_id', 'post_id', 'type'])
                            ->each(function (PostReaction $legacy): void {
                                Reaction::query()->firstOrCreate([
                                    'user_id' => $legacy->getAttribute('user_id'),
                                    'reactable_type' => Post::class,
                                    'reactable_id' => $legacy->getAttribute('post_id'),
                                ], [
                                    'type' => Reaction::normalizeType((string) $legacy->getAttribute('type')),
                                ]);
                            });
                    }

                    if ($hasLikes) {
                        Like::query()
                            ->where('post_id', $post->getKey())
                            ->get(['user_id', 'post_id'])
                            ->each(function (Like $legacy): void {
                                Reaction::query()->firstOrCreate([
                                    'user_id' => $legacy->getAttribute('user_id'),
                                    'reactable_type' => Post::class,
                                    'reactable_id' => $legacy->getAttribute('post_id'),
                                ], [
                                    'type' => Reaction::TYPE_LOVE,
                                ]);
                            });
                    }
                }
            });
    }

    private function alignOwnerMembership(Group $group, int $ownerId): bool
    {
        $membership = GroupMember::query()
            ->where('group_id', $group->getKey())
            ->where('user_id', $ownerId)
            ->first();

        if (! $membership) {
            GroupMember::query()->create([
                'group_id' => $group->getKey(),
                'user_id' => $ownerId,
                'role' => GroupMemberRole::Owner->value,
                'status' => GroupMemberStatus::Active->value,
                'joined_at' => now(),
            ]);

            return true;
        }

        $updates = [];

        if ((string) ($membership->role?->value ?? '') !== GroupMemberRole::Owner->value) {
            $updates['role'] = GroupMemberRole::Owner->value;
        }

        if ((string) ($membership->status?->value ?? '') !== GroupMemberStatus::Active->value) {
            $updates['status'] = GroupMemberStatus::Active->value;
        }

        if (! $membership->joined_at) {
            $updates['joined_at'] = now();
        }

        if ($updates === []) {
            return false;
        }

        $membership->forceFill($updates)->save();

        return true;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, string>
     */
    private function pathsFromOptions(array $options): array
    {
        $path = $options['path'] ?? null;

        if (is_array($path)) {
            return array_values(array_filter($path, static fn (mixed $value): bool => is_string($value) && $value !== ''));
        }

        if (is_string($path) && $path !== '') {
            return [$path];
        }

        return [];
    }
}
