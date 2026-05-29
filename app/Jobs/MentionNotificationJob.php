<?php

namespace App\Jobs;

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Notifications\MentionedInPost;
use App\Services\VisibilityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MentionNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $postId,
        public readonly int $mentionedUserId,
        public readonly int $authorId,
    ) {
        $this->afterCommit();
    }

    public function handle(VisibilityService $visibility): void
    {
        $post = Post::query()->with('author')->find($this->postId);
        $mentionedUser = User::query()
            ->select(['id', 'name', 'username', 'notification_preferences'])
            ->find($this->mentionedUserId);
        $author = User::query()
            ->select(['id', 'name', 'username'])
            ->find($this->authorId);

        if (! $post instanceof Post || ! $mentionedUser instanceof User || ! $author instanceof User) {
            return;
        }

        if ((int) $mentionedUser->getKey() === (int) $author->getKey()) {
            return;
        }

        if (! $mentionedUser->notificationEnabled('mentions')) {
            return;
        }

        if ($mentionedUser->hasBlockingRelationshipWith($author)) {
            return;
        }

        if (! $visibility->canView($mentionedUser, $post)) {
            return;
        }

        $mentionedUser->notify(new MentionedInPost($author, $post));
    }
}
