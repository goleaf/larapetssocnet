# Threaded Comments Rendering

## Loading Pattern

Thread loading is centralized in `CommentService::paginateThread()`.
It loads top-level comments, replies, and reactions in one structured query set.

```php
$comments = app(CommentService::class)
    ->paginateThread($post, $viewer, 20);
```

Profile media modals use:

```php
$comments = app(CommentService::class)
    ->threadForPost($post, $viewer);
```

Inline feed comment panels use:

```php
$comments = app(CommentService::class)
    ->previewThread($post, $viewer, CommentService::PREVIEW_TOP_LEVEL_LIMIT, CommentService::PREVIEW_REPLY_LIMIT);
```

Implementation details:
- Top-level comments: `topLevel()` scope (`parent_id` is null).
- Replies and reply-to-reply comments are eager loaded through the `replies` relation.
- `withTrashed()` keeps tombstoned comments visible in-thread.
- Reaction summaries, current viewer reaction, and up to five Paw/Love reactor avatar faces are hydrated in-memory after pagination.
- Thread subscription state is hydrated from `comment_thread_subscriptions` for the loaded root comments.
- Visible Livewire polling should use `CommentService::threadActivity()` and compare the returned fingerprint before dispatching parent card refresh events.
- The feed card mounts one `posts.comments-thread` Livewire component only after comments open; individual comments stay recursive Blade partials.

## Tombstones
- Soft-deleted comments remain in the thread.
- Body and body_html are replaced with `[comment removed]`.
- Replies remain visible under the tombstone.

## Reply UX
- Replies are created by posting `parent_id` to `POST /posts/{post}/comments`.
- Feed inline replies are created through `posts.comments-thread`, which calls the same comment actions as the HTTP route.
- Comment Paw/Love reactions are handled by a parent `posts.comments-thread` action accepting the comment ID and reaction type.
- Three visual levels are allowed: top-level, direct reply, and reply-to-reply.
- New replies targeting an already third-level comment are flattened onto that third-level parent by `CommentService`.

## Pagination
- Inline feed panels show three quality-weighted top-level comments by default, append three more via `loadMoreComments()`, and show two recent replies per top-level comment until that reply thread is expanded.
- Full post pages mount the same Livewire component with `fullPage=true`, render the loaded thread through `CommentService::threadForPost()`, and support Top/Newest/Oldest sorting without a page reload. Busy threads expose debounced comment search through `CommentService::searchWithinPost()`.
- HTTP pagination remains available through `CommentService::paginateThread()` for non-Livewire contexts.
- Always chain `->withQueryString()`.

## Collapse
Top-level comments can collapse/expand replies with Alpine state.
