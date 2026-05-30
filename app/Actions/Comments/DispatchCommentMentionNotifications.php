<?php

namespace App\Actions\Comments;

use App\Enums\PostStatus;
use App\Enums\Support\Queue\QueueName;
use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\VisibilityService;
use App\Support\Queue\HasDefaultQueueRuntime;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class DispatchCommentMentionNotifications implements ShouldBeUnique, ShouldQueue
{
    use HasDefaultQueueRuntime;
    use Queueable;

    /**
     * Columns required to evaluate notification preferences and post visibility without hydrating full users.
     *
     * @var list<string>
     */
    private const USER_VISIBILITY_COLUMNS = [
        'users.id',
        'users.name',
        'users.username',
        'users.notification_preferences',
        'users.is_private',
        'users.is_banned',
        'users.scheduled_deletion_at',
        'users.deactivated_at',
        'users.suspended_until',
        'users.pets_visibility',
    ];

    /**
     * @var list<string>
     */
    private const POST_NOTIFICATION_COLUMNS = [
        'posts.id',
        'posts.user_id',
        'posts.pet_id',
        'posts.body',
        'posts.status',
        'posts.published_at',
        'posts.visibility',
    ];

    /**
     * @var list<string>
     */
    private const COMMENT_NOTIFICATION_COLUMNS = [
        'comments.id',
        'comments.post_id',
        'comments.user_id',
        'comments.body',
        'comments.deleted_at',
    ];

    /**
     * @var list<string>
     */
    private const PET_VISIBILITY_COLUMNS = [
        'pets.id',
        'pets.user_id',
        'pets.visibility',
        'pets.is_public',
    ];

    /**
     * @param  list<string>  $mentionedUsernames
     * @param  list<int>  $excludedUserIds
     */
    public function __construct(
        public readonly int $authorId,
        public readonly int $postId,
        public readonly int $commentId,
        public readonly array $mentionedUsernames,
        public readonly array $excludedUserIds = [],
    ) {
        $this->onQueue(QueueName::Comments->routingName());
    }

    public function uniqueId(): string
    {
        return 'comment-mentions:'.$this->commentId;
    }

    public function handle(VisibilityService $visibility): void
    {
        $usernames = collect($this->mentionedUsernames)
            ->map(fn (string $username): string => strtolower(trim($username)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($usernames === []) {
            return;
        }

        $author = User::query()
            ->select(self::USER_VISIBILITY_COLUMNS)
            ->whereKey($this->authorId)
            ->first();
        $post = Post::query()
            ->select(self::POST_NOTIFICATION_COLUMNS)
            ->with($this->postVisibilityRelations())
            ->whereKey($this->postId)
            ->first();
        $comment = Comment::withTrashed()
            ->select(self::COMMENT_NOTIFICATION_COLUMNS)
            ->whereKey($this->commentId)
            ->first();

        if (! $author instanceof User || ! $post instanceof Post || ! $comment instanceof Comment || $comment->trashed()) {
            return;
        }

        if ($author->isUnavailableForProfile() || ! $this->isPublishedForNotifications($post)) {
            return;
        }

        $excludedUserIds = collect($this->excludedUserIds)
            ->push((int) $author->getKey())
            ->map(fn (int|string $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $jobs = $this->eligibleMentionRecipients($usernames, $excludedUserIds, $author, $post)
            ->map(fn (User $recipient): SendCommentMentionNotification => new SendCommentMentionNotification(
                (int) $this->authorId,
                (int) $this->postId,
                (int) $this->commentId,
                (int) $recipient->getKey(),
            ))
            ->values()
            ->all();

        if ($jobs === []) {
            return;
        }

        Bus::batch($jobs)
            ->name('comment-'.$this->commentId.'-mentions')
            ->allowFailures()
            ->dispatch();
    }

    /**
     * @param  list<string>  $usernames
     * @param  list<int>  $excludedUserIds
     * @return Collection<int, User>
     */
    private function eligibleMentionRecipients(array $usernames, array $excludedUserIds, User $author, Post $post): Collection
    {
        $query = User::query()
            ->select(self::USER_VISIBILITY_COLUMNS)
            ->whereIn('username', $usernames)
            ->whereNotIn('id', $excludedUserIds)
            ->with([
                'roles' => fn ($roleQuery) => $roleQuery->select(['roles.id', 'roles.name']),
            ]);

        User::applyAvailableForProfiles($query);

        /** @var Collection<int, User> $recipients */
        $recipients = $query->get();

        if ($recipients->isEmpty()) {
            return $recipients;
        }

        $recipientIds = $recipients
            ->map(fn (User $recipient): int => (int) $recipient->getKey())
            ->values();

        $blockedRecipientIds = $this->idLookup($this->blockedRecipientIds($recipientIds, $author, $post));
        $followsAuthorIds = $this->idLookup($this->acceptedFollowingIds($recipientIds, (int) $author->getKey()));
        $authorFollowsRecipientIds = $this->idLookup(
            $this->postVisibility($post) === Post::VISIBILITY_FRIENDS
                ? $this->acceptedFollowerIds((int) $author->getKey(), $recipientIds)
                : []
        );
        $petOwnerFollowerIds = $this->idLookup($this->petOwnerFollowerIds($recipientIds, $post));
        $petFollowerIds = $this->idLookup($this->petFollowerIds($recipientIds, $post));
        $petCoOwnerIds = $this->idLookup($this->petCoOwnerIds($recipientIds, $post));

        return $recipients
            ->filter(function (User $recipient) use ($post, $author, $blockedRecipientIds, $followsAuthorIds, $authorFollowsRecipientIds, $petOwnerFollowerIds, $petFollowerIds, $petCoOwnerIds): bool {
                $recipientId = (int) $recipient->getKey();

                if (! $recipient->notificationEnabled('mentions')) {
                    return false;
                }

                $isModerator = $this->hasModeratorRole($recipient);

                if ($isModerator) {
                    return true;
                }

                if (isset($blockedRecipientIds[$recipientId])) {
                    return false;
                }

                if (! $this->recipientCanViewPet($recipient, $post, $petOwnerFollowerIds, $petFollowerIds, $petCoOwnerIds)) {
                    return false;
                }

                $followsAuthor = isset($followsAuthorIds[$recipientId]);
                $isFriend = $followsAuthor && isset($authorFollowsRecipientIds[$recipientId]);

                return match ($this->postVisibility($post)) {
                    Post::VISIBILITY_PUBLIC => ! (bool) $author->is_private || $followsAuthor,
                    Post::VISIBILITY_FOLLOWERS => $followsAuthor,
                    Post::VISIBILITY_FRIENDS => $isFriend,
                    default => false,
                };
            })
            ->values();
    }

    /**
     * @param  Collection<int, int>  $recipientIds
     * @return list<int>
     */
    private function blockedRecipientIds(Collection $recipientIds, User $author, Post $post): array
    {
        if ($recipientIds->isEmpty() || ! User::hasBlocksTable()) {
            return [];
        }

        $targetUserIds = collect([(int) $author->getKey(), $this->petOwnerId($post)])
            ->filter(fn (?int $id): bool => $id !== null && $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($targetUserIds === []) {
            return [];
        }

        $recipientIdList = $recipientIds->all();

        return DB::table('blocks')
            ->where(function ($query) use ($recipientIdList, $targetUserIds): void {
                $query
                    ->whereIn('blocker_id', $recipientIdList)
                    ->whereIn('blocked_id', $targetUserIds);
            })
            ->orWhere(function ($query) use ($recipientIdList, $targetUserIds): void {
                $query
                    ->whereIn('blocked_id', $recipientIdList)
                    ->whereIn('blocker_id', $targetUserIds);
            })
            ->get(['blocker_id', 'blocked_id'])
            ->map(fn (object $block): int => in_array((int) $block->blocker_id, $recipientIdList, true)
                ? (int) $block->blocker_id
                : (int) $block->blocked_id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, int>  $recipientIds
     * @return list<int>
     */
    private function acceptedFollowingIds(Collection $recipientIds, int $followingUserId): array
    {
        if ($recipientIds->isEmpty() || $followingUserId <= 0) {
            return [];
        }

        return DB::table('follows')
            ->whereIn('follower_id', $recipientIds->all())
            ->where('following_id', $followingUserId)
            ->where('status', 'accepted')
            ->pluck('follower_id')
            ->map(fn (int|string $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, int>  $recipientIds
     * @return list<int>
     */
    private function acceptedFollowerIds(int $followerUserId, Collection $recipientIds): array
    {
        if ($recipientIds->isEmpty() || $followerUserId <= 0) {
            return [];
        }

        return DB::table('follows')
            ->where('follower_id', $followerUserId)
            ->whereIn('following_id', $recipientIds->all())
            ->where('status', 'accepted')
            ->pluck('following_id')
            ->map(fn (int|string $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, int>  $recipientIds
     * @return list<int>
     */
    private function petOwnerFollowerIds(Collection $recipientIds, Post $post): array
    {
        $petOwnerId = $this->petOwnerId($post);

        return $petOwnerId !== null ? $this->acceptedFollowingIds($recipientIds, $petOwnerId) : [];
    }

    /**
     * @param  Collection<int, int>  $recipientIds
     * @return list<int>
     */
    private function petFollowerIds(Collection $recipientIds, Post $post): array
    {
        $pet = $post->pet;

        if (! $pet instanceof Pet || $recipientIds->isEmpty()) {
            return [];
        }

        return DB::table('pet_followers')
            ->where('pet_id', $pet->getKey())
            ->whereIn('user_id', $recipientIds->all())
            ->pluck('user_id')
            ->map(fn (int|string $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, int>  $recipientIds
     * @return list<int>
     */
    private function petCoOwnerIds(Collection $recipientIds, Post $post): array
    {
        $pet = $post->pet;

        if (! $pet instanceof Pet || $recipientIds->isEmpty()) {
            return [];
        }

        return DB::table('pet_owners')
            ->where('pet_id', $pet->getKey())
            ->whereIn('user_id', $recipientIds->all())
            ->whereNotNull('accepted_at')
            ->pluck('user_id')
            ->map(fn (int|string $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, true>
     */
    private function idLookup(array $ids): array
    {
        return array_fill_keys(array_map(fn (int|string $id): int => (int) $id, $ids), true);
    }

    /**
     * @param  array<int, true>  $petOwnerFollowerIds
     * @param  array<int, true>  $petFollowerIds
     * @param  array<int, true>  $petCoOwnerIds
     */
    private function recipientCanViewPet(User $recipient, Post $post, array $petOwnerFollowerIds, array $petFollowerIds, array $petCoOwnerIds): bool
    {
        $pet = $post->pet;

        if (! $pet instanceof Pet) {
            return true;
        }

        $owner = $pet->owner;

        if (! $owner instanceof User || $owner->isUnavailableForProfile()) {
            return false;
        }

        $recipientId = (int) $recipient->getKey();
        $ownerId = (int) $owner->getKey();

        if ($recipientId === $ownerId || isset($petCoOwnerIds[$recipientId])) {
            return true;
        }

        if (($owner->pets_visibility ?: 'everyone') === 'followers_only' && ! isset($petOwnerFollowerIds[$recipientId])) {
            return false;
        }

        return match ($this->petVisibility($pet)) {
            'public' => true,
            'followers_only' => isset($petFollowerIds[$recipientId]),
            default => false,
        };
    }

    private function hasModeratorRole(User $recipient): bool
    {
        if (! $recipient->relationLoaded('roles')) {
            return false;
        }

        return $recipient->roles->contains(
            fn (object $role): bool => in_array((string) data_get($role, 'name'), ['admin', 'moderator'], true)
        );
    }

    private function isPublishedForNotifications(Post $post): bool
    {
        $status = $post->getAttribute('status');

        if (! $status instanceof PostStatus || ! $status->isPubliclyReachable()) {
            return false;
        }

        $publishedAt = $post->getAttribute('published_at');

        if (! $publishedAt) {
            return true;
        }

        return $publishedAt instanceof CarbonInterface
            ? $publishedAt->isPast()
            : now()->greaterThanOrEqualTo($publishedAt);
    }

    private function postVisibility(Post $post): string
    {
        $visibility = $post->getAttribute('visibility');

        return $visibility instanceof BackedEnum ? (string) $visibility->value : (string) $visibility;
    }

    private function petVisibility(Pet $pet): string
    {
        $visibility = (string) ($pet->getRawOriginal('visibility') ?: $pet->getAttribute('visibility'));

        if (in_array($visibility, Pet::VISIBILITY, true)) {
            return $visibility;
        }

        $rawIsPublic = $pet->getRawOriginal('is_public');

        return in_array($rawIsPublic, [0, '0', false], true) ? 'private' : 'public';
    }

    private function petOwnerId(Post $post): ?int
    {
        $pet = $post->pet;

        if (! $pet instanceof Pet || ! $pet->owner instanceof User) {
            return null;
        }

        return (int) $pet->owner->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    private function postVisibilityRelations(): array
    {
        return [
            'author' => fn ($query) => $query->select(self::USER_VISIBILITY_COLUMNS),
            'pet' => fn ($query) => $query
                ->select(self::PET_VISIBILITY_COLUMNS)
                ->with([
                    'owner' => fn ($ownerQuery) => $ownerQuery->select(self::USER_VISIBILITY_COLUMNS),
                ]),
        ];
    }
}
