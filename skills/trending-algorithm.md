# Trending Algorithm

Trending posts are computed at query time.
No stored trending score column.

- Window: last 48 hours.
- Minimum engagement: at least one like or comment.
- Ordering: highest `likes_count`, then `comments_count`, then newest first.

## Post Scope

```php
public function scopeTrending(Builder $query): Builder
{
    return $query
        ->where('created_at', '>=', now()->subHours(48))
        ->where(function (Builder $scoreQuery): void {
            $scoreQuery
                ->where('likes_count', '>', 0)
                ->orWhere('comments_count', '>', 0);
        })
        ->orderByDesc('likes_count')
        ->orderByDesc('comments_count')
        ->orderByDesc('created_at');
}
```

## Top Rated Scope

```php
public function scopeTopRated(Builder $query): Builder
{
    return $query->orderByDesc('likes_count')->orderByDesc('created_at');
}
```
