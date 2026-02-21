# Trending Algorithm

Trending posts are computed at query time.
No stored trending score column.

- Score formula: `likes_count + (comments_count * 2)`.
- Window: last 48 hours.
- Minimum engagement: at least one interaction.
- Tie-breaker: newer post first.

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
        // Approved exception: computed ordering has no Eloquent equivalent.
        ->orderByRaw('(likes_count + (comments_count * 2)) DESC, created_at DESC');
}
```

## Top Rated Scope

```php
public function scopeTopRated(Builder $query): Builder
{
    return $query->orderByDesc('likes_count')->orderByDesc('created_at');
}
```
