<?php

namespace App\Services;

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Notifications\MentionedInPost;

class PostMentionNotificationService
{
    public function __construct(private readonly VisibilityService $visibility) {}

    public function send(int $postId, int $mentionedUserId, int $authorId): void
    {
        $post = Post::query()->with('author')->find($postId);
        $mentionedUser = User::query()
            ->select(['id', 'name', 'username', 'notification_preferences'])
            ->find($mentionedUserId);
        $author = User::query()
            ->select(['id', 'name', 'username'])
            ->find($authorId);

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

        if (! $this->visibility->canView($mentionedUser, $post)) {
            return;
        }

        $mentionedUser->notify(new MentionedInPost($author, $post));
    }
}
