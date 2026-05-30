# Query Optimization

Prevent N+1 in social-network pages.

## Detection
- Use Debugbar in local.
- Use `DB::listen()` in local/dev.
- Use `DB::whenQueryingForLongerThan()` only in local, testing, or explicit performance monitoring contexts.
- Assert query counts in tests for critical endpoints.
- Keep `Model::preventLazyLoading(! app()->isProduction())` enabled so local and test runs catch accidental relation access.
- Treat Laravel automatic eager loading as beta; it may help exploratory development, but explicit eager loading remains mandatory for production-critical list surfaces.

## Rules
- Every Eloquent list query must define its relation graph, selected parent and relation columns, pagination strategy, deterministic sort order, and aggregate counts/existence flags before rendering.
- Select only the parent columns and relation columns the view needs. When constraining eager-load columns, include `id` and every foreign key required by the relationship.
- Use `withCount()`, `withExists()`, `withSum()`, batched `pluck()->flip()`, or precomputed counter caches instead of per-row `count()`, `exists()`, reaction, saved, follow, or policy-adjacent lookups.
- Use `withAvg()`, `withMin()`, and `withMax()` when those aggregate values are needed by the UI.
- Use `withWhereHas()` when filtering and eager loading the same relationship.
- Queue dispatchers that fan out notifications must batch eligibility checks (blocks, follows, roles, visibility, notification preferences) before creating jobs; do not call relationship/policy helpers inside per-recipient loops. Bursty fan-out dispatchers must also be unique, overlap-protected, debounced, or idempotent before they create duplicate user-visible notifications.
- Use `cursorPaginate()` for feeds and infinite scroll, ordered by stable unique columns. Use `paginate()` only when totals are required.
- Cache compact arrays/read models with explicit keys and invalidation paths instead of arbitrary graph-loaded Eloquent objects. Use cache tags only with a fallback for cache stores that do not support tags.
- Use `Cache::memo()` for repeated stable cache reads within one request/job and `Cache::touch()` when only TTL extension is needed. Do not call `Cache::flush()` on shared stores.
- Feed pages should eager load all required relations in one `with([...])` chain.
- Main feed reads should keep source membership in SQL through the unioned `Post::forFeed($viewerId, $source)` candidate ID subquery, then hydrate posts through the outer Eloquent query for ordering, eager loading, and cursor pagination. Apply `feed_mutes(user_id, mutable_type, mutable_id)` exclusions through indexed `NOT EXISTS` subqueries instead of filtering muted posts in PHP.
- Best-ranked feed reads may order by a raw database score expression, but the base candidate set must still use existing published/group/feed indexes and cursor pagination.
- Empty feed suggestions should use the viewer's pet species list to run one filtered `users` query with a `whereHas('pets')` species predicate; never load all users or all pets for recommendation scoring.
- Profile pages should eager load user media, pet card media, and count data before rendering.
- Profile Pets tab queries should eager load pet media and add `viewer_is_following` with `withExists` against `pet_followers`; do not call `isFollowingPet()` inside card loops.
- Profile About pet summary strips should query visible pets once, eager-load only avatar media, and avoid owner/species/breed/tag default eager-loads when the strip only renders linked avatars and names.
- Profile About mutual connection and "Also followed by" strips should join or subquery the accepted `follows` rows for the viewer and profile owner, select only the user columns required for linked avatars, and cap the display query instead of loading full user records or entire follower collections.
- Profile post grids should eager load both `media` and `postMedia`, and media-only filters should use indexed `EXISTS` checks instead of loading all posts and filtering collections.
- Profile portfolio pages should read selected rows through `profile_portfolio_posts(user_id, display_order, post_id)` and then join posts by primary key; settings management may cap eligible public posts to a small recent window and must not load a user's full post history just to build the selector.
- Profile previews that compare social graph data should use SQL joins/subqueries, not PHP collection intersections over whole follower lists.
- Profile followers/following modals should query the accepted relationship selected by the reusable modal mode only after list visibility passes, apply name/username search inside that relationship query, eager-load media, load 20-row batches through modal-scoped infinite scroll, batch viewer follow statuses, compute mutual counts with SQL subqueries, and return the locked state before building the relationship query for unauthorized private-account viewers.
- Profile completeness should read only the columns/counts/media-exists flags it needs instead of loading full user records.
- Profile view analytics should use the `profile_views(profile_user_id, viewed_on)` index for current and previous 30-day date-window scans, count distinct signed-in `viewer_user_id` values only for the owner-facing quiet note and trend, and skip the aggregate entirely for visitor renders.
- Profile Wrapped should be generated once into `profile_wrapped_summaries` per user/year, then profile renders should do only the owner/window summary lookup. Annual generation queries must use the wrapped reactions and comments date indexes instead of recomputing expensive aggregates during profile page loads.
- Pet profile owner lookups should use `pets(user_id, created_at)`, normalized species discovery should use `pets(species_id, created_at)`, milestone timelines should use `pet_milestones(pet_id, occurred_on)`, breed autocomplete should use `breeds(species_id, normalized_name, name)` with prefix matching, birthday notifications should use `pets(birthday_month_day, is_archived, deleted_at)`, health reminder dispatch should use `pet_health_reminders(next_due_on, pet_id)`, and weight history reads should use `pet_weight_entries(pet_id, entry_date)`.
- Account security device-session lists should read `sessions` through the `(user_id, last_activity)` index, select only `id`, `ip_address`, `user_agent`, and `last_activity`, and enrich device/location labels from local parsers instead of issuing remote calls during render.
- Login anomaly checks should scan `auth_audit_logs(user_id, created_at)` for the last 90 days, avoid full-table history scans, and deduplicate unresolved `login_security_alerts` by user, country, and recent creation window.
- Onboarding follow suggestions should select only display/avatar/count columns, exclude already-followed and pending users at the query layer, use the onboarding suggestions users index for top-account ordering, and batch follow-status lookups through the existing follows composite index.
- Never access relation properties inside loops unless eager loaded.
- Do not use raw queries for performance unless the model/query-builder alternative is inadequate and the exception is documented with test coverage.

## Correct patterns
```php
Post::query()
    ->forFeed($viewerId, $source)
    ->with(['author.media', 'media', 'hashtags'])
    ->withCount(['comments', 'likes'])
    ->cursorPaginate(15);

User::query()
    ->with(['media'])
    ->withCount(['followers', 'posts', 'pets'])
    ->paginate(24);
```
