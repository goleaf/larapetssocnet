# Search Architecture

Search uses Eloquent with LIKE-based matching.
No Scout, no Meilisearch.

## Post Search Scope

```php
public function scopeSearch(Builder $query, string $term): Builder
{
    $clean = Str::limit(trim($term), 100);

    return $query->where(function (Builder $searchQuery) use ($clean): void {
        $searchQuery
            ->where('body', 'like', "%{$clean}%")
            ->orWhereHas('hashtags', fn (Builder $hashtagQuery) => $hashtagQuery->where('name', 'like', "%{$clean}%"))
            ->orWhere('location', 'like', "%{$clean}%");
    });
}
```

## User Search Scope
Use the existing user scope pattern for name, username, bio, and location.

## Global vs Explore Search
- `/search`: global entity search.
- `/explore?q=`: posts-only search within Explore.
- Both routes require authentication; guest requests should redirect to login before search logic runs.

## Sanitization
- Trim input.
- Limit to 100 characters.
- Keep parameter binding through Eloquent.
- Never interpolate user input into raw SQL.
