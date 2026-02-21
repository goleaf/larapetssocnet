# Query Optimization

Prevent N+1 in social-network pages.

## Detection
- Use Debugbar in local.
- Use `DB::listen()` in local/dev.
- Assert query counts in tests for critical endpoints.

## Rules
- Feed pages should eager load all required relations in one `with([...])` chain.
- Profile pages should eager load user media and related entities.
- Never access relation properties inside loops unless eager loaded.

## Correct patterns
```php
Post::with(['author.media', 'media', 'hashtags'])
    ->withCount(['comments', 'reactions'])
    ->paginate(15);

User::with(['media'])
    ->withCount(['followers', 'posts', 'pets'])
    ->paginate(24);
```
