<?php

namespace App\Services;

use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class PinService
{
    /**
     * @throws AuthorizationException
     */
    public function pin(User $actor, Post $post): Post
    {
        $this->assertOwner($actor, $post);

        return DB::transaction(function () use ($post): Post {
            User::query()
                ->whereKey($post->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            Post::query()
                ->where('user_id', $post->user_id)
                ->where('is_pinned', true)
                ->update(['is_pinned' => false, 'pinned_at' => null]);

            $post->updateQuietly(['is_pinned' => true, 'pinned_at' => now()]);

            return $post->refresh();
        });
    }

    /**
     * @throws AuthorizationException
     */
    public function unpin(User $actor, Post $post): Post
    {
        $this->assertOwner($actor, $post);

        $post->updateQuietly(['is_pinned' => false, 'pinned_at' => null]);

        return $post->refresh();
    }

    /**
     * @throws AuthorizationException
     */
    public function toggle(User $actor, Post $post): Post
    {
        return $post->is_pinned
            ? $this->unpin($actor, $post)
            : $this->pin($actor, $post);
    }

    /**
     * @throws AuthorizationException
     */
    private function assertOwner(User $actor, Post $post): void
    {
        if ((int) $post->user_id !== (int) $actor->getKey()) {
            throw new AuthorizationException('Only the owner can pin this post.');
        }
    }
}
