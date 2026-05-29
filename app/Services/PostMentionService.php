<?php

namespace App\Services;

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Notifications\MentionedInPost;
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

    public function sync(Post $post, User $author): void
    {
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

        if ($mentionedUsers->isEmpty()) {
            return;
        }

        DB::afterCommit(function () use ($post, $author, $mentionedUsers): void {
            $post->loadMissing('author');

            foreach ($mentionedUsers as $mentionedUser) {
                if ((int) $mentionedUser->getKey() === (int) $author->getKey()) {
                    continue;
                }

                if (! $mentionedUser->notificationEnabled('mentions')) {
                    continue;
                }

                if ($mentionedUser->hasBlockingRelationshipWith($author)) {
                    continue;
                }

                if (! app(VisibilityService::class)->canView($mentionedUser, $post)) {
                    continue;
                }

                $mentionedUser->notify(new MentionedInPost($author, $post));
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
