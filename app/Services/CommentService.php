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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommentService
{
    public const MAX_BODY_LENGTH = 500;

    public const PREVIEW_TOP_LEVEL_LIMIT = 3;

    public const PREVIEW_REPLY_LIMIT = 2;

    public const MAX_THREAD_DEPTH = 3;

    public function __construct(
        private readonly ContentService $content,
        private readonly MentionService $mentions,
        private readonly VisibilityService $visibility,
        private readonly CommentLanguageService $languages,
        private readonly CommentQualityService $quality,
        private readonly CommentThreadSubscriptionService $threadSubscriptions,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Comment>
     */
    public function paginateThread(Post $post, ?User $viewer, int $perPage = 20, string $sort = 'oldest'): LengthAwarePaginator
    {
        $viewerId = (int) ($viewer?->getKey() ?? 0);

        $query = $this->threadQuery($post, $viewer, replyLimit: null, nestedReplyLimit: null);

        $this->applyThreadSort($query, $sort);

        $comments = $query->paginate($perPage)->withQueryString();

        $comments->setCollection($this->hydrateThreadMetadata($comments->getCollection(), $viewerId, $viewer));

        return $comments;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function threadForPost(Post $post, ?User $viewer, string $sort = 'oldest'): Collection
    {
        $viewerId = (int) ($viewer?->getKey() ?? 0);
        $query = $this->threadQuery($post, $viewer, replyLimit: null, nestedReplyLimit: null);

        $this->applyThreadSort($query, $sort);

        return $this->hydrateThreadMetadata(
            $query->get(),
            $viewerId,
            $viewer
        );
    }

    /**
     * @return Collection<int, Comment>
     */
    public function searchWithinPost(Post $post, ?User $viewer, string $term): Collection
    {
        $term = trim($term);

        if ($term === '') {
            return collect();
        }

        $viewerId = (int) ($viewer?->getKey() ?? 0);
        $likeTerm = '%'.addcslashes($term, '%_\\').'%';

        $comments = Comment::query()
            ->threadColumns()
            ->where('comments.post_id', $post->getKey())
            ->where('comments.body', 'like', $likeTerm)
            ->visibleTo($viewer)
            ->with([
                'user' => fn ($userQuery) => $userQuery->select([
                    'users.id',
                    'users.name',
                    'users.username',
                    'users.avatar_path',
                    'users.profile_photo_path',
                ]),
                'user.media',
                'reactions.user' => fn ($reactionUserQuery) => $reactionUserQuery->select([
                    'users.id',
                    'users.name',
                    'users.username',
                    'users.avatar_path',
                    'users.profile_photo_path',
                ]),
                'reactions.user.media',
            ])
            ->orderByDesc('comments.quality_score')
            ->latest('comments.created_at')
            ->limit(50)
            ->get()
            ->each(fn (Comment $comment): Comment => $comment->setRelation('replies', collect()));

        return $this->hydrateThreadMetadata($comments, $viewerId, $viewer, $term);
    }

    /**
     * @param  array<int, bool|int|string>  $expandedReplyIds
     * @return Collection<int, Comment>
     */
    public function previewThread(
        Post $post,
        ?User $viewer,
        int $limit = self::PREVIEW_TOP_LEVEL_LIMIT,
        int $replyLimit = self::PREVIEW_REPLY_LIMIT,
        array $expandedReplyIds = [],
    ): Collection {
        $viewerId = (int) ($viewer?->getKey() ?? 0);

        $comments = $this->hydrateThreadMetadata(
            $this->threadQuery($post, $viewer, replyLimit: $replyLimit, nestedReplyLimit: $replyLimit)
                ->orderByDesc('comments.quality_score')
                ->latest('comments.created_at')
                ->limit($limit)
                ->get(),
            $viewerId,
            $viewer
        );

        return $this->replaceExpandedReplies($comments, $post, $viewer, $expandedReplyIds, $viewerId);
    }

    public function topLevelCount(Post $post, ?User $viewer): int
    {
        return Comment::query()
            ->where('comments.post_id', $post->getKey())
            ->topLevel()
            ->visibleTo($viewer)
            ->withTrashed()
            ->count();
    }

    public function inlineComment(Post $post, ?User $viewer, int $commentId, int $replyLimit = self::PREVIEW_REPLY_LIMIT): ?Comment
    {
        $viewerId = (int) ($viewer?->getKey() ?? 0);
        $comment = Comment::query()
            ->threadColumns()
            ->where('comments.post_id', $post->getKey())
            ->whereKey($commentId)
            ->visibleTo($viewer)
            ->withTrashed()
            ->with([
                'user' => fn ($userQuery) => $userQuery->select([
                    'users.id',
                    'users.name',
                    'users.username',
                    'users.avatar_path',
                    'users.profile_photo_path',
                ]),
                'user.media',
                'reactions.user' => fn ($reactionUserQuery) => $reactionUserQuery->select([
                    'users.id',
                    'users.name',
                    'users.username',
                    'users.avatar_path',
                    'users.profile_photo_path',
                ]),
                'reactions.user.media',
                'replies' => fn ($replyQuery) => $this->replyQuery($replyQuery, $viewer, $replyLimit)
                    ->with([
                        'replies' => fn ($nestedReplyQuery) => $this->replyQuery($nestedReplyQuery, $viewer, $replyLimit),
                    ]),
            ])
            ->first();

        return $comment instanceof Comment ? $this->hydrateCommentMetadata($comment, $viewerId, 1) : null;
    }

    /**
     * @return array{visible_count: int, latest_id: int, latest_updated_at: ?string}
     */
    public function threadActivity(Post $post, ?User $viewer): array
    {
        $summary = Comment::query()
            ->where('comments.post_id', $post->getKey())
            ->visibleTo($viewer)
            ->withTrashed()
            ->selectRaw('COUNT(*) as visible_count, COALESCE(MAX(comments.id), 0) as latest_id, MAX(comments.updated_at) as latest_updated_at')
            ->first();

        return [
            'visible_count' => (int) ($summary?->getAttribute('visible_count') ?? 0),
            'latest_id' => (int) ($summary?->getAttribute('latest_id') ?? 0),
            'latest_updated_at' => $summary?->getAttribute('latest_updated_at') !== null
                ? (string) $summary->getAttribute('latest_updated_at')
                : null,
        ];
    }

    /**
     * @param  array{gif_url?: string|null, gif_preview_url?: string|null, gif_title?: string|null, gif_provider?: string|null}|null  $gif
     */
    public function create(Post $post, User $author, string $body, ?Comment $parent = null, ?array $gif = null): Comment
    {
        $body = $this->normalizeBody($body);
        $gif = $this->normalizeGifPayload($gif);

        if ($body === '' && $gif === null) {
            throw ValidationException::withMessages([
                'body' => 'Comment cannot be empty.',
            ]);
        }

        $parent = $parent instanceof Comment ? $this->normalizedParentForReply($post, $parent) : null;

        return DB::transaction(function () use ($post, $author, $body, $parent, $gif): Comment {
            $comment = Comment::query()->create([
                'post_id' => $post->getKey(),
                'user_id' => $author->getKey(),
                'parent_id' => $parent?->getKey(),
                'body' => $body,
                'body_html' => $this->content->process($body),
                'gif_url' => $gif['gif_url'] ?? null,
                'gif_preview_url' => $gif['gif_preview_url'] ?? null,
                'gif_title' => $gif['gif_title'] ?? null,
                'gif_provider' => $gif['gif_provider'] ?? null,
                'language_code' => $this->languages->detect($body),
                'quality_score' => $this->quality->score($body),
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
            'language_code' => $this->languages->detect($body),
            'quality_score' => $this->quality->score($body, (int) $comment->reactions_count),
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
        $this->quality->refresh($comment);

        return $reaction;
    }

    /**
     * @param  Collection<int, Comment>  $comments
     * @return Collection<int, Comment>
     */
    private function hydrateThreadMetadata($comments, int $viewerId, ?User $viewer = null, ?string $searchTerm = null)
    {
        $subscriptionMap = $this->threadSubscriptions->subscribedRootMap($viewer, $comments);

        return $comments->map(fn (Comment $comment): Comment => $this->hydrateCommentMetadata($comment, $viewerId, 1, $subscriptionMap, null, $searchTerm));
    }

    /**
     * @return Builder<Comment>
     */
    private function threadQuery(Post $post, ?User $viewer, ?int $replyLimit, ?int $nestedReplyLimit): Builder
    {
        return Comment::query()
            ->threadColumns()
            ->where('comments.post_id', $post->getKey())
            ->topLevel()
            ->visibleTo($viewer)
            ->withTrashed()
            ->with([
                'user' => fn ($userQuery) => $userQuery->select([
                    'users.id',
                    'users.name',
                    'users.username',
                    'users.avatar_path',
                    'users.profile_photo_path',
                ]),
                'user.media',
                'reactions.user' => fn ($reactionUserQuery) => $reactionUserQuery->select([
                    'users.id',
                    'users.name',
                    'users.username',
                    'users.avatar_path',
                    'users.profile_photo_path',
                ]),
                'reactions.user.media',
                'replies' => fn ($replyQuery) => $this->replyQuery($replyQuery, $viewer, $replyLimit)
                    ->with([
                        'replies' => fn ($nestedReplyQuery) => $this->replyQuery($nestedReplyQuery, $viewer, $nestedReplyLimit),
                    ]),
            ]);
    }

    /**
     * @param  HasMany<Comment, Comment>  $replyQuery
     * @return HasMany<Comment, Comment>
     */
    private function replyQuery(HasMany $replyQuery, ?User $viewer, ?int $limit): HasMany
    {
        $replyQuery
            ->threadColumns()
            ->visibleTo($viewer)
            ->withTrashed();

        if ($limit !== null) {
            $replyQuery->latest('comments.created_at')->limit($limit);
        } else {
            $replyQuery->oldest('comments.created_at');
        }

        $replyQuery->with([
            'user' => fn ($replyUserQuery) => $replyUserQuery->select([
                'users.id',
                'users.name',
                'users.username',
                'users.avatar_path',
                'users.profile_photo_path',
            ]),
            'user.media',
            'reactions.user' => fn ($reactionUserQuery) => $reactionUserQuery->select([
                'users.id',
                'users.name',
                'users.username',
                'users.avatar_path',
                'users.profile_photo_path',
            ]),
            'reactions.user.media',
        ]);

        return $replyQuery;
    }

    /**
     * @param  Builder<Comment>  $query
     */
    private function applyThreadSort(Builder $query, string $sort): void
    {
        match ($sort) {
            'newest' => $query->latest('comments.created_at'),
            'top' => $query->orderByDesc('comments.quality_score')->orderByDesc('comments.reactions_count')->latest('comments.created_at'),
            default => $query->oldest('comments.created_at'),
        };
    }

    /**
     * @param  array<int, bool>  $subscriptionMap
     */
    private function hydrateCommentMetadata(
        Comment $comment,
        int $viewerId,
        int $depth,
        array $subscriptionMap = [],
        ?int $rootId = null,
        ?string $searchTerm = null,
    ): Comment {
        $rootId ??= $comment->parent_id === null ? (int) $comment->getKey() : null;

        $comment->setAttribute('reaction_summary', $this->summarizeReactions($comment));
        $comment->setAttribute('current_viewer_reaction', $this->resolveViewerReaction($comment, $viewerId));
        $comment->setAttribute('reaction_reactors', $this->reactionReactors($comment));
        $comment->setAttribute('thread_depth', $depth);
        $comment->setAttribute('thread_root_id', $rootId);
        $comment->setAttribute('is_thread_subscribed', $rootId !== null && (bool) ($subscriptionMap[$rootId] ?? false));
        $comment->setAttribute('should_translate', $this->languages->shouldTranslate(
            filled($comment->language_code) ? (string) $comment->language_code : null,
            app()->getLocale(),
        ));

        if (filled($searchTerm)) {
            $comment->setAttribute('highlighted_body_html', $this->highlightSearchTerm((string) $comment->body, (string) $searchTerm));
        }

        if (! $comment->relationLoaded('replies')) {
            return $comment;
        }

        $comment->setRelation('replies', $comment->replies->map(
            fn ($reply) => $reply instanceof Comment
                ? $this->hydrateCommentMetadata($reply, $viewerId, $depth + 1, $subscriptionMap, $rootId, $searchTerm)
                : $reply
        ));

        return $comment;
    }

    /**
     * @param  Collection<int, Comment>  $comments
     * @param  array<int, bool|int|string>  $expandedReplyIds
     * @return Collection<int, Comment>
     */
    private function replaceExpandedReplies(Collection $comments, Post $post, ?User $viewer, array $expandedReplyIds, int $viewerId): Collection
    {
        $expandedIds = collect($expandedReplyIds)
            ->filter()
            ->keys()
            ->merge(collect($expandedReplyIds)->filter(fn ($value): bool => is_int($value) || is_string($value))->values())
            ->map(fn ($value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values();

        if ($expandedIds->isEmpty()) {
            return $comments;
        }

        $fullReplyGroups = Comment::query()
            ->threadColumns()
            ->where('comments.post_id', $post->getKey())
            ->whereIn('comments.parent_id', $expandedIds->all())
            ->visibleTo($viewer)
            ->withTrashed()
            ->oldest('comments.created_at')
            ->with([
                'user' => fn ($replyUserQuery) => $replyUserQuery->select([
                    'users.id',
                    'users.name',
                    'users.username',
                    'users.avatar_path',
                    'users.profile_photo_path',
                ]),
                'user.media',
                'reactions.user' => fn ($reactionUserQuery) => $reactionUserQuery->select([
                    'users.id',
                    'users.name',
                    'users.username',
                    'users.avatar_path',
                    'users.profile_photo_path',
                ]),
                'reactions.user.media',
                'replies' => fn ($nestedReplyQuery) => $this->replyQuery($nestedReplyQuery, $viewer, null),
            ])
            ->get()
            ->groupBy('parent_id');

        $subscriptionMap = $this->threadSubscriptions->subscribedRootMap($viewer, $comments);

        $replace = function (Comment $comment, int $depth) use (&$replace, $fullReplyGroups, $viewerId, $subscriptionMap): Comment {
            $rootId = (int) ($comment->thread_root_id ?? ($comment->parent_id === null ? $comment->getKey() : 0));

            if ($fullReplyGroups->has($comment->getKey())) {
                $comment->setRelation(
                    'replies',
                    $fullReplyGroups
                        ->get($comment->getKey())
                        ->map(fn (Comment $reply): Comment => $this->hydrateCommentMetadata($reply, $viewerId, $depth + 1, $subscriptionMap, $rootId > 0 ? $rootId : null))
                );
            }

            if ($comment->relationLoaded('replies')) {
                $comment->setRelation('replies', $comment->replies->map(fn ($reply) => $reply instanceof Comment ? $replace($reply, $depth + 1) : $reply));
            }

            return $comment;
        };

        return $comments->map(fn (Comment $comment): Comment => $replace($comment, (int) ($comment->thread_depth ?? 1)));
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

    /**
     * @return list<array{id: int, name: string, username: string, avatar_url: ?string, type: string}>
     */
    private function reactionReactors(Comment $comment): array
    {
        if (! $comment->relationLoaded('reactions')) {
            return [];
        }

        return $comment->reactions
            ->filter(fn ($reaction): bool => $reaction instanceof Reaction && in_array(Reaction::normalizeType((string) $reaction->getAttribute('type')), Reaction::commentTypes(), true))
            ->sortByDesc('created_at')
            ->take(5)
            ->map(function ($reaction): ?array {
                $user = $reaction->user;

                if (! $user instanceof User) {
                    return null;
                }

                return [
                    'id' => (int) $user->getKey(),
                    'name' => (string) $user->name,
                    'username' => (string) $user->username,
                    'avatar_url' => is_string($user->getAttribute('avatar_url')) ? $user->getAttribute('avatar_url') : null,
                    'type' => Reaction::normalizeType((string) $reaction->getAttribute('type')),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function resolveViewerReaction(Comment $comment, int $viewerId): ?string
    {
        if ($viewerId < 1 || ! $comment->relationLoaded('reactions')) {
            return null;
        }

        $reaction = $comment->reactions
            ->firstWhere('user_id', $viewerId);
        $reactionType = $reaction instanceof Reaction ? $reaction->getAttribute('type') : null;

        return $reactionType ? Reaction::normalizeType((string) $reactionType) : null;
    }

    private function normalizeBody(string $body): string
    {
        $body = trim($body);

        if (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            throw ValidationException::withMessages([
                'body' => 'Comments may not be longer than 500 characters.',
            ]);
        }

        return $body;
    }

    /**
     * @param  array{gif_url?: string|null, gif_preview_url?: string|null, gif_title?: string|null, gif_provider?: string|null}|null  $gif
     * @return array{gif_url: string, gif_preview_url: string|null, gif_title: string|null, gif_provider: string|null}|null
     */
    private function normalizeGifPayload(?array $gif): ?array
    {
        $url = trim((string) ($gif['gif_url'] ?? ''));

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $previewUrl = trim((string) ($gif['gif_preview_url'] ?? ''));

        return [
            'gif_url' => $url,
            'gif_preview_url' => $previewUrl !== '' && filter_var($previewUrl, FILTER_VALIDATE_URL) ? $previewUrl : null,
            'gif_title' => filled($gif['gif_title'] ?? null) ? Str::limit((string) $gif['gif_title'], 120, '') : null,
            'gif_provider' => filled($gif['gif_provider'] ?? null) ? Str::limit((string) $gif['gif_provider'], 32, '') : null,
        ];
    }

    private function highlightSearchTerm(string $body, string $term): string
    {
        $term = trim($term);
        $escapedBody = e($body);

        if ($term === '') {
            return nl2br($escapedBody);
        }

        $highlighted = preg_replace(
            '/('.preg_quote(e($term), '/').')/iu',
            '<mark class="rounded-sm bg-amber-light px-0.5 text-bark">$1</mark>',
            $escapedBody,
        );

        return nl2br(is_string($highlighted) ? $highlighted : $escapedBody);
    }

    private function normalizedParentForReply(Post $post, Comment $parent): Comment
    {
        if ((int) $parent->post_id !== (int) $post->getKey()) {
            throw ValidationException::withMessages([
                'parent_id' => 'Reply target must belong to the same post.',
            ]);
        }

        if ($parent->trashed()) {
            throw ValidationException::withMessages([
                'parent_id' => 'Cannot reply to a removed comment.',
            ]);
        }

        while ($this->commentDepth($parent) >= self::MAX_THREAD_DEPTH - 1 && $parent->parent_id !== null) {
            $parent = Comment::query()
                ->where('comments.post_id', $post->getKey())
                ->whereKey($parent->parent_id)
                ->firstOrFail();
        }

        return $parent;
    }

    private function commentDepth(Comment $comment): int
    {
        $depth = 0;
        $current = $comment;

        while ($current->parent_id !== null && $depth < self::MAX_THREAD_DEPTH + 5) {
            $depth++;
            $current = Comment::query()
                ->select(['comments.id', 'comments.parent_id', 'comments.post_id', 'comments.user_id', 'comments.body', 'comments.body_html', 'comments.deleted_at'])
                ->whereKey($current->parent_id)
                ->firstOrFail();
        }

        return $depth;
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

        $notifiedUserIds = $this->threadSubscriptions->notifySubscribers($author, $post, $comment, $parent, $notifiedUserIds);
        $this->threadSubscriptions->syncAuthorSubscription($author, $post, $comment);

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
