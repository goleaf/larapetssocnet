# Query Optimization

Prevent N+1 in social-network pages.

## Detection
- Use Debugbar in local.
- Use `DB::listen()` in local/dev.
- Assert query counts in tests for critical endpoints.

## Rules
- Feed pages should eager load all required relations in one `with([...])` chain.
- Profile pages should eager load user media, pet card media, and count data before rendering.
- Profile Pets tab queries should eager load pet media and add `viewer_is_following` with `withExists` against `pet_followers`; do not call `isFollowingPet()` inside card loops.
- Profile About pet summary strips should query visible pets once, eager-load only avatar media, and avoid owner/species/breed/tag default eager-loads when the strip only renders linked avatars and names.
- Profile post grids should eager load both `media` and `postMedia`, and media-only filters should use indexed `EXISTS` checks instead of loading all posts and filtering collections.
- Profile previews that compare social graph data should use SQL joins/subqueries, not PHP collection intersections over whole follower lists.
- Profile completeness should read only the columns/counts/media-exists flags it needs instead of loading full user records.
- Never access relation properties inside loops unless eager loaded.

## Correct patterns
```php
Post::query()
    ->forFeed($viewerId)
    ->with(['author.media', 'media', 'hashtags'])
    ->withCount(['comments', 'likes'])
    ->cursorPaginate(15);

User::query()
    ->with(['media'])
    ->withCount(['followers', 'posts', 'pets'])
    ->paginate(24);
```
