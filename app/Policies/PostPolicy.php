<?php

namespace App\Policies;

use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\VisibilityService;

class PostPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Post $post): bool
    {
        return app(VisibilityService::class)->canViewPost($user, $post);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Post $post): bool
    {
        if ($user->id !== $post->user_id) {
            return false;
        }

        $status = $post->status instanceof PostStatus ? $post->status : PostStatus::tryFrom((string) $post->status);

        if (in_array($status, [PostStatus::Draft, PostStatus::Scheduled], true)) {
            return true;
        }

        return $post->created_at === null || $post->created_at->greaterThanOrEqualTo(now()->subDay());
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->id === $post->user_id || $user->hasRole('admin');
    }

    public function pin(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }

    public function unpin(User $user, Post $post): bool
    {
        return $this->pin($user, $post);
    }

    public function publish(User $user, Post $post): bool
    {
        return $this->update($user, $post);
    }

    public function schedule(User $user, Post $post): bool
    {
        return $this->update($user, $post);
    }

    public function unpublish(User $user, Post $post): bool
    {
        return $this->update($user, $post);
    }

    public function archive(User $user, Post $post): bool
    {
        return $this->update($user, $post);
    }

    public function attachPet(User $user, Post $post): bool
    {
        return $this->update($user, $post);
    }

    public function react(User $user, Post $post): bool
    {
        if ($post->belongsToArchivedGroup()) {
            return false;
        }

        if ($user->hasBlockingRelationshipWith($post->author)) {
            return false;
        }

        return $this->view($user, $post);
    }

    public function save(User $user, Post $post): bool
    {
        return $this->react($user, $post);
    }

    public function share(User $user, Post $post): bool
    {
        return $this->react($user, $post);
    }

    public function report(User $user, Post $post): bool
    {
        if ($user->id === $post->user_id) {
            return false;
        }

        return $this->view($user, $post);
    }
}
