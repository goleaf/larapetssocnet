<?php

namespace App\Services;

use App\Models\Content\Comment;
use App\Models\Content\CommentThreadSubscription;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Notifications\Database\Comments\NewCommentThreadReply;
use Illuminate\Support\Collection;

class CommentThreadSubscriptionService
{
    /**
     * @param  array<int, int>  $alreadyNotifiedUserIds
     * @return array<int, int>
     */
    public function notifySubscribers(
        User $author,
        Post $post,
        Comment $comment,
        ?Comment $parent,
        array $alreadyNotifiedUserIds = [],
    ): array {
        if (! $parent instanceof Comment) {
            return $alreadyNotifiedUserIds;
        }

        $root = $this->rootComment($parent);
        $notifiedUserIds = $alreadyNotifiedUserIds;

        CommentThreadSubscription::query()
            ->where('root_comment_id', $root->getKey())
            ->whereNull('unsubscribed_at')
            ->with([
                'user' => fn ($query) => $query->select([
                    'users.id',
                    'users.name',
                    'users.username',
                    'users.notification_preferences',
                ]),
            ])
            ->get()
            ->each(function (CommentThreadSubscription $subscription) use ($author, $post, $comment, $root, &$notifiedUserIds): void {
                $recipient = $subscription->user;

                if (! $recipient instanceof User) {
                    return;
                }

                if ((int) $recipient->getKey() === (int) $author->getKey()) {
                    return;
                }

                if (in_array((int) $recipient->getKey(), $notifiedUserIds, true)) {
                    return;
                }

                if (! $recipient->notificationEnabled('comment_thread_replies')) {
                    return;
                }

                if ($recipient->hasBlockingRelationshipWith($author)) {
                    return;
                }

                if (! $post->canBeViewedBy($recipient)) {
                    return;
                }

                $recipient->notify(new NewCommentThreadReply($author, $post, $root, $comment));
                $notifiedUserIds[] = (int) $recipient->getKey();
            });

        return $notifiedUserIds;
    }

    public function syncAuthorSubscription(User $author, Post $post, Comment $comment): void
    {
        $root = $this->rootComment($comment);
        $threadIds = $this->threadCommentIds($root);

        $authorCommentCount = Comment::query()
            ->whereIn('comments.id', $threadIds)
            ->where('comments.user_id', $author->getKey())
            ->whereNull('comments.deleted_at')
            ->count();

        if ($authorCommentCount < 2) {
            return;
        }

        $subscription = CommentThreadSubscription::query()
            ->firstOrNew([
                'user_id' => $author->getKey(),
                'root_comment_id' => $root->getKey(),
            ]);

        if ($subscription->exists) {
            return;
        }

        $subscription->forceFill([
            'post_id' => $post->getKey(),
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ])->save();
    }

    public function unsubscribe(User $user, Comment $comment): void
    {
        $root = $this->rootComment($comment);

        $subscription = CommentThreadSubscription::query()
            ->firstOrNew([
                'user_id' => $user->getKey(),
                'root_comment_id' => $root->getKey(),
            ]);

        $subscription->forceFill([
            'post_id' => $root->post_id,
            'subscribed_at' => $subscription->subscribed_at,
            'unsubscribed_at' => now(),
        ])->save();
    }

    /**
     * @param  Collection<int, Comment>  $comments
     * @return array<int, bool>
     */
    public function subscribedRootMap(?User $viewer, Collection $comments): array
    {
        if (! $viewer instanceof User) {
            return [];
        }

        $rootIds = $this->collectRootIds($comments);

        if ($rootIds === []) {
            return [];
        }

        return CommentThreadSubscription::query()
            ->where('user_id', $viewer->getKey())
            ->whereIn('root_comment_id', $rootIds)
            ->whereNull('unsubscribed_at')
            ->pluck('root_comment_id')
            ->mapWithKeys(fn (int|string $rootId): array => [(int) $rootId => true])
            ->all();
    }

    public function rootComment(Comment $comment): Comment
    {
        $current = $comment;

        while ($current->parent_id !== null) {
            $current = Comment::query()
                ->withTrashed()
                ->whereKey($current->parent_id)
                ->firstOrFail();
        }

        return $current;
    }

    /**
     * @return list<int>
     */
    private function threadCommentIds(Comment $root): array
    {
        $rootId = (int) $root->getKey();
        $firstLevelIds = Comment::withTrashed()
            ->where('parent_id', $rootId)
            ->pluck('id')
            ->map(fn (int|string $id): int => (int) $id)
            ->all();

        $secondLevelIds = $firstLevelIds === []
            ? []
            : Comment::withTrashed()
                ->whereIn('parent_id', $firstLevelIds)
                ->pluck('id')
                ->map(fn (int|string $id): int => (int) $id)
                ->all();

        return array_values(array_unique([
            $rootId,
            ...$firstLevelIds,
            ...$secondLevelIds,
        ]));
    }

    /**
     * @param  Collection<int, Comment>  $comments
     * @return list<int>
     */
    private function collectRootIds(Collection $comments): array
    {
        $rootIds = [];

        $collect = function (Collection $items) use (&$collect, &$rootIds): void {
            foreach ($items as $comment) {
                if (! $comment instanceof Comment) {
                    continue;
                }

                $rootIds[] = (int) ($comment->thread_root_id ?? ($comment->parent_id === null ? $comment->getKey() : 0));

                if ($comment->relationLoaded('replies')) {
                    $collect($comment->replies);
                }
            }
        };

        $collect($comments);

        return collect($rootIds)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
