<?php

namespace App\Services;

use App\Jobs\MentionNotificationJob;
use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PostMentionService
{
    /**
     * @return list<string>
     */
    public function extractUsernames(string $text): array
    {
        preg_match_all('/@([A-Za-z0-9_-]{3,30})/', $text, $matches);

        return array_values(array_unique(array_map(
            static fn (string $username): string => strtolower($username),
            $matches[1] ?? [],
        )));
    }

    public function sync(Post $post, User $author, bool $dispatchNotifications = true, bool $notifyExistingMentions = false): void
    {
        $existingMentionedUserIds = $post->mentionedUsers()
            ->pluck('users.id')
            ->map(static fn (mixed $userId): int => (int) $userId);

        $usernames = $this->extractUsernames((string) $post->getAttribute('body'));

        if ($usernames === []) {
            $post->mentionedUsers()->detach();

            return;
        }

        $mentionedUsers = $this->resolveMentionedUsers($usernames);
        $payload = $mentionedUsers
            ->mapWithKeys(fn (User $user): array => [
                $user->getKey() => ['mentioned_username' => strtolower((string) $user->getAttribute('username'))],
            ])
            ->all();

        $post->mentionedUsers()->sync($payload);

        if ($mentionedUsers->isEmpty() || ! $dispatchNotifications) {
            return;
        }

        $newlyMentionedUsers = $notifyExistingMentions
            ? $mentionedUsers->values()
            : $mentionedUsers
                ->reject(fn (User $mentionedUser): bool => $existingMentionedUserIds->contains((int) $mentionedUser->getKey()))
                ->values();

        if ($newlyMentionedUsers->isEmpty()) {
            return;
        }

        DB::afterCommit(function () use ($post, $author, $newlyMentionedUsers): void {
            foreach ($newlyMentionedUsers as $mentionedUser) {
                if ((int) $mentionedUser->getKey() === (int) $author->getKey()) {
                    continue;
                }

                MentionNotificationJob::dispatch(
                    postId: (int) $post->getKey(),
                    mentionedUserId: (int) $mentionedUser->getKey(),
                    authorId: (int) $author->getKey(),
                )->afterCommit();
            }
        });
    }

    /**
     * @param  list<string>  $usernames
     * @return Collection<int, User>
     */
    public function resolveMentionedUsers(array $usernames): Collection
    {
        if ($usernames === []) {
            return User::query()->whereKey(-1)->get();
        }

        return User::query()
            ->whereIn('username', $usernames)
            ->get(['id', 'username', 'name', 'notification_preferences']);
    }
}
