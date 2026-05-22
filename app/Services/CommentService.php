<?php

namespace App\Services;

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use App\Notifications\MentionedInComment;
use App\Notifications\NewComment;
use App\Notifications\NewCommentReply;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommentService
{
    public const MAX_BODY_LENGTH = 1000;

    public function __construct(
        private readonly ContentService $content,
        private readonly MentionService $mentions,
        private readonly VisibilityService $visibility,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Comment>
     */
    public function paginateThread(Post $post, ?User $viewer, int $perPage = 20): LengthAwarePaginator
    {
        $viewerId = (int) ($viewer?->getKey() ?? 0);

        $comments = $this->threadQuery($post, $viewer)
            ->oldest('comments.created_at')
            ->paginate($perPage)
            ->withQueryString();

        $comments->setCollection($this->hydrateThreadMetadata($comments->getCollection(), $viewerId));

        return $comments;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function threadForPost(Post $post, ?User $viewer): Collection
    {
        $viewerId = (int) ($viewer?->getKey() ?? 0);

        return $this->hydrateThreadMetadata(
            $this->threadQuery($post, $viewer)
                ->oldest('comments.created_at')
                ->get(),
            $viewerId
        );
    }

    public function create(Post $post, User $author, string $body, ?Comment $parent = null): Comment
    {
        $body = $this->normalizeBody($body);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => 'Comment cannot be empty.',
            ]);
        }

        if ($parent instanceof Comment) {
            $this->assertValidParent($post, $parent);
        }

        return DB::transaction(function () use ($post, $author, $body, $parent): Comment {
            $comment = Comment::query()->create([
                'post_id' => $post->getKey(),
                'user_id' => $author->getKey(),
                'parent_id' => $parent?->getKey(),
                'body' => $body,
                'body_html' => $this->content->process($body),
            ]);

            DB::afterCommit(function () use ($author, $post, $comment, $parent): void {
                $this->notifyOnCreate($author, $post, $comment, $parent);
            });

            return $comment->refresh();
        });
    }

    public function update(Comment $comment, string $body): Comment
    {
        $body = $this->normalizeBody($body);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => 'Comment cannot be empty.',
            ]);
        }

        if ($body === (string) $comment->body) {
            return $comment;
        }

        $comment->update([
            'body' => $body,
            'body_html' => $this->content->process($body),
            'edited_at' => now(),
        ]);

        return $comment->refresh();
    }

    public function delete(Comment $comment): void
    {
        if ($comment->trashed()) {
            return;
        }

        $comment->forceFill([
            'body' => '[comment removed]',
            'body_html' => '[comment removed]',
        ])->save();

        $comment->delete();
    }

    public function toggleReaction(Comment $comment, User $user, string $type): ?Reaction
    {
        $reaction = $comment->toggleReaction($user, $type);
        $comment->refresh();

        return $reaction;
    }

    /**
     * @param  Collection<int, Comment>  $comments
     * @return Collection<int, Comment>
     */
    private function hydrateThreadMetadata($comments, int $viewerId)
    {
        return $comments->map(function (Comment $comment) use ($viewerId): Comment {
            $comment->setAttribute('reaction_summary', $this->summarizeReactions($comment));
            $comment->setAttribute('current_viewer_reaction', $this->resolveViewerReaction($comment, $viewerId));

            $replies = $comment->replies->map(function (Comment $reply) use ($viewerId): Comment {
                $reply->setAttribute('reaction_summary', $this->summarizeReactions($reply));
                $reply->setAttribute('current_viewer_reaction', $this->resolveViewerReaction($reply, $viewerId));

                return $reply;
            });

            $comment->setRelation('replies', $replies);

            return $comment;
        });
    }

    /**
     * @return Builder<Comment>
     */
    private function threadQuery(Post $post, ?User $viewer): Builder
    {
        return Comment::query()
            ->threadColumns()
            ->where('comments.post_id', $post->getKey())
            ->topLevel()
            ->visibleTo($viewer)
            ->withTrashed()
            ->with([
                'user' => fn (BelongsTo $userQuery): BelongsTo => $userQuery->select([
                    'users.id',
                    'users.name',
                    'users.username',
                    'users.avatar_path',
                    'users.profile_photo_path',
                ]),
                'user.media',
                'replies' => fn (HasMany $replyQuery): HasMany => $replyQuery
                    ->threadColumns()
                    ->visibleTo($viewer)
                    ->withTrashed()
                    ->oldest('comments.created_at')
                    ->with([
                        'user' => fn (BelongsTo $replyUserQuery): BelongsTo => $replyUserQuery->select([
                            'users.id',
                            'users.name',
                            'users.username',
                            'users.avatar_path',
                            'users.profile_photo_path',
                        ]),
                        'user.media',
                        'reactions',
                    ]),
                'reactions',
            ]);
    }

    /**
     * @return array<string, int>
     */
    private function summarizeReactions(Comment $comment): array
    {
        if (! $comment->relationLoaded('reactions')) {
            return [];
        }

        return $comment->reactions
            ->groupBy(fn (Reaction $reaction): string => Reaction::normalizeType($reaction->type))
            ->map(fn ($group): int => $group->count())
            ->filter(fn (int $count): bool => $count > 0)
            ->sortDesc()
            ->all();
    }

    private function resolveViewerReaction(Comment $comment, int $viewerId): ?string
    {
        if ($viewerId < 1 || ! $comment->relationLoaded('reactions')) {
            return null;
        }

        $reactionType = $comment->reactions
            ->firstWhere('user_id', $viewerId)
            ?->type;

        return $reactionType ? Reaction::normalizeType((string) $reactionType) : null;
    }

    private function normalizeBody(string $body): string
    {
        return trim($body);
    }

    private function assertValidParent(Post $post, Comment $parent): void
    {
        if ((int) $parent->post_id !== (int) $post->getKey()) {
            throw ValidationException::withMessages([
                'parent_id' => 'Reply target must belong to the same post.',
            ]);
        }

        if ($parent->parent_id !== null) {
            throw ValidationException::withMessages([
                'parent_id' => 'Only one reply level is allowed.',
            ]);
        }

        if ($parent->trashed()) {
            throw ValidationException::withMessages([
                'parent_id' => 'Cannot reply to a removed comment.',
            ]);
        }
    }

    private function notifyOnCreate(User $author, Post $post, Comment $comment, ?Comment $parent): void
    {
        $notifiedUserIds = [];

        if (! $parent instanceof Comment) {
            $post->loadMissing('author');

            if ((int) $post->user_id !== (int) $author->getKey()) {
                $recipient = $post->author;

                if ($recipient
                    && $recipient->notificationEnabled('post_comments')
                    && ! $recipient->hasBlockingRelationshipWith($author)
                ) {
                    $recipient->notify(new NewComment($author, $post, $comment));
                    $notifiedUserIds[] = (int) $recipient->getKey();
                }
            }
        } else {
            $parent->loadMissing('user');

            if ((int) $parent->user_id !== (int) $author->getKey()) {
                $recipient = $parent->user;

                if ($recipient
                    && $recipient->notificationEnabled('comment_replies')
                    && $this->visibility->canView($recipient, $post)
                    && ! $recipient->hasBlockingRelationshipWith($author)
                ) {
                    $recipient->notify(new NewCommentReply($author, $post, $parent, $comment));
                    $notifiedUserIds[] = (int) $recipient->getKey();
                }
            }
        }

        $mentions = $this->mentions->extractMentions($comment->body);

        if ($mentions === []) {
            return;
        }

        $mentionRecipients = User::query()
            ->whereIn('username', $mentions)
            ->get(['id', 'username', 'name', 'notification_preferences']);

        foreach ($mentionRecipients as $recipient) {
            if ((int) $recipient->getKey() === (int) $author->getKey()) {
                continue;
            }

            if (in_array((int) $recipient->getKey(), $notifiedUserIds, true)) {
                continue;
            }

            if (! $recipient->notificationEnabled('mentions')) {
                continue;
            }

            if ($recipient->hasBlockingRelationshipWith($author)) {
                continue;
            }

            if (! $this->visibility->canView($recipient, $post)) {
                continue;
            }

            $recipient->notify(new MentionedInComment($author, $post, $comment));
        }
    }
}
