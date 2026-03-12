# Threaded Comments Rendering

## Loading Pattern

Thread loading is centralized in `CommentService::paginateThread()`.
It loads top-level comments, replies, and reactions in one structured query set.

```php
$comments = app(CommentService::class)
    ->paginateThread($post, $viewer, 20);
```

Implementation details:
- Top-level comments: `topLevel()` scope (`parent_id` is null).
- Replies are eager loaded via `replies` relation.
- `withTrashed()` keeps tombstoned comments visible in-thread.
- Reaction summaries are hydrated in-memory after pagination.

## Tombstones
- Soft-deleted comments remain in the thread.
- Body and body_html are replaced with `[comment removed]`.
- Replies remain visible under the tombstone.

## Reply UX
- Replies are created by posting `parent_id` to `POST /posts/{post}/comments`.
- Only one reply level is allowed (enforced in `CommentService`).

## Pagination
- Paginate only top-level comments (`20/page`).
- Replies are not paginated.
- Always chain `->withQueryString()`.

## Collapse
Top-level comments can collapse/expand replies with Alpine state.
