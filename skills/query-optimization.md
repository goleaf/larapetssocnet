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
- Profile About mutual connection and "Also followed by" strips should join or subquery the accepted `follows` rows for the viewer and profile owner, select only the user columns required for linked avatars, and cap the display query instead of loading full user records or entire follower collections.
- Profile post grids should eager load both `media` and `postMedia`, and media-only filters should use indexed `EXISTS` checks instead of loading all posts and filtering collections.
- Profile previews that compare social graph data should use SQL joins/subqueries, not PHP collection intersections over whole follower lists.
- Profile followers/following modals should query the accepted relationship selected by the reusable modal mode, apply name/username search inside that relationship query, eager-load media, load 20-row batches through modal-scoped infinite scroll, batch viewer follow statuses, compute mutual counts with SQL subqueries, and enforce list visibility before rendering.
- Profile completeness should read only the columns/counts/media-exists flags it needs instead of loading full user records.
- Profile view analytics should use the `profile_views(profile_user_id, viewed_on)` index for current and previous 30-day date-window scans, count distinct signed-in `viewer_user_id` values only for the owner-facing quiet note and trend, and skip the aggregate entirely for visitor renders.
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
