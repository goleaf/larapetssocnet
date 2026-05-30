<?php

namespace App\Actions\Comments;

use App\Enums\Support\Queue\QueueName;
use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Notifications\Database\Comments\MentionedInComment;
use App\Services\VisibilityService;
use App\Support\Queue\HasDefaultQueueRuntime;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;

class SendCommentMentionNotification implements ShouldBeUnique, ShouldQueue
{
    use Batchable;
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

    public function __construct(
        public readonly int $authorId,
        public readonly int $postId,
        public readonly int $commentId,
        public readonly int $recipientId,
    ) {
        $this->onQueue(QueueName::Comments->routingName());
    }

    public function uniqueId(): string
    {
        return 'comment-mention:'.$this->commentId.':'.$this->recipientId;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->uniqueId()))
                ->releaseAfter(30)
                ->expireAfter(300),
        ];
    }

    public function handle(VisibilityService $visibility): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $author = User::query()
            ->select(self::USER_VISIBILITY_COLUMNS)
            ->whereKey($this->authorId)
            ->first();
        $recipient = User::query()
            ->select(self::USER_VISIBILITY_COLUMNS)
            ->whereKey($this->recipientId)
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

        if (! $author instanceof User || ! $recipient instanceof User || ! $post instanceof Post || ! $comment instanceof Comment || $comment->trashed()) {
            return;
        }

        if (! $recipient->notificationEnabled('mentions')) {
            return;
        }

        if ($recipient->hasBlockingRelationshipWith($author)) {
            return;
        }

        if (! $visibility->canView($recipient, $post)) {
            return;
        }

        if ($this->alreadyNotified($recipient)) {
            return;
        }

        $recipient->notify(new MentionedInComment($author, $post, $comment));
    }

    private function alreadyNotified(User $recipient): bool
    {
        return DB::table('notifications')
            ->where('type', MentionedInComment::class)
            ->where('notifiable_type', $recipient->getMorphClass())
            ->where('notifiable_id', $recipient->getKey())
            ->where('data->comment_id', $this->commentId)
            ->exists();
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
