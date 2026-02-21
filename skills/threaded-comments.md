# Threaded Comments Rendering

## Loading Pattern

Use eager loading for top-level comments and replies in one structured query:

```php
$comments = $post->comments()
    ->whereNull('parent_id')
    ->with([
        'author.media',
        'reactions',
        'replies' => fn ($query) => $query
            ->with(['author.media', 'reactions'])
            ->withCount('reactions')
            ->oldest(),
    ])
    ->withCount('reactions')
    ->oldest()
    ->paginate(20);
```

## Tombstones

For soft-deleted comments:

- Show placeholder text `[comment removed]` styled muted/italic.
- Keep replies visible under tombstoned parent.

## Reply UX

- Reply action only on top-level comments.
- Inline reply form toggled with Alpine.
- Submit reply through `POST /comments/{comment}/replies`.
- On success: append reply and close form.

## Pagination

- Paginate only top-level comments (`20/page`).
- Replies are not paginated.
- Provide load-more behavior preserving scroll position.

## Collapse

Top-level comments can collapse/expand replies:

- `x-data="{ collapsed: false }"`
- Collapsed state shows `Show N replies` trigger.
