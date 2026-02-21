# Feed Architecture

The feed is the most read-heavy page in the app.
Every decision must optimize for read performance.

## Feed Query Strategy
- Simple pull-based feed. No fan-out and no pre-computed tables.
- On each request: query posts from followed users plus own posts.
- Filter by visibility, block relationships, and account status.
- Order by `created_at DESC` (newest first).
- Paginate with page-based pagination (not cursor-based).
- Eager load everything needed in one query set.
- Never lazy load inside the feed loop.

## What Belongs In The Feed
- Own posts (all visibilities).
- Posts from accepted-following users where visibility is `public`.
- Posts from accepted-following users where visibility is `followers`.
- Pinned posts are not elevated in feed order. Pinning only affects profile pages.

## What Never Appears In Feed
- Posts from blocked users in both directions.
- Posts from banned users.
- Posts from private accounts that are not followed.
- Private-visibility posts from other users.
- Soft-deleted posts.

## Feed Scope
- Define `scopeForFeed(User $user)` on `Post`.
- Use it from `FeedService`; do not duplicate logic in controller.
- Always chain `->with([...])->withCount([...])` after the scope, never inside the scope.

## Pagination
- Use 15 posts per page.
- Use `->paginate(15)` and not `simplePaginate`.
- Preserve query string on pagination links.
- Show `You're all caught up!` on last page when total posts are under 50.

## Empty State
- New user following 0 people:
  - Show `Your feed is empty` and suggestions widget.
  - Show `Who to Follow` prominently.
  - Show `Explore` link to public posts.
- Following users with no posts:
  - Same layout with a different message.

## Performance Rules
- Target max 3 DB queries for feed page load:
  1. Fetch paginated posts with eager loads.
  2. Fetch who-to-follow suggestions.
  3. Fetch trending hashtags for sidebar.
- Measure via query count assertions in tests.
- In local development, log queries exceeding 100ms via `DB::listen()`.
