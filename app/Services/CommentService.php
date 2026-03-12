<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CommentService
{
    public function create(Post $post, User $author, string $body, ?int $parentId = null): Comment
    {
        if ($parentId !== null) {
            $this->assertValidParent($post, $parentId);
        }

        $comment = Comment::query()->create([
            'post_id' => $post->getKey(),
            'user_id' => $author->getKey(),
            'parent_id' => $parentId,
            'body' => trim($body),
        ]);

        $post->refreshCommentsCount();

        return $comment->refresh();
    }

    public function update(Comment $comment, string $body): Comment
    {
        $comment->update([
            'body' => trim($body),
            'edited_at' => now(),
        ]);

        return $comment->refresh();
    }

    public function delete(Comment $comment): void
    {
        $commentIds = [$comment->getKey()];

        if ($comment->parent_id === null) {
            $replyIds = Comment::query()
                ->select(['id'])
                ->where('post_id', $comment->post_id)
                ->where('parent_id', $comment->getKey())
                ->pluck('id')
                ->all();

            $commentIds = [...$commentIds, ...$replyIds];
        }

        Comment::query()
            ->whereIn('id', $commentIds)
            ->delete();

        $post = Post::query()
            ->select(['id', 'comments_count'])
            ->whereKey($comment->post_id)
            ->first();

        $post?->refreshCommentsCount();
    }

    public function toggleReaction(Comment $comment, User $user, string $type): ?Reaction
    {
        $reaction = $comment->toggleReaction($user, $type);
        $comment->refresh();

        return $reaction;
    }

    private function assertValidParent(Post $post, int $parentId): void
    {
        $parent = Comment::query()
            ->select(['id', 'post_id', 'parent_id'])
            ->where('post_id', $post->getKey())
            ->whereKey($parentId)
            ->firstOrFail();

        if ($parent->parent_id !== null) {
            throw ValidationException::withMessages([
                'parent_id' => 'Only one reply level is allowed.',
            ]);
        }
    }
}
