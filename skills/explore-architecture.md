# Explore Architecture

Explore is the public discovery surface.
It is accessible to guests and authenticated users.
No login is required to browse Explore.

## What Explore Shows
- All public posts from public (non-private) accounts.
- Posts ordered by the selected sort strategy.
- No posts from banned users.
- No posts from private accounts.
- For authenticated users, no posts from blocked users.
- For authenticated users, no posts from users who blocked them.

## What Explore Never Shows
- Followers-visibility posts.
- Private-visibility posts.
- Posts from private accounts even if the post is public.
- Soft-deleted posts.
- Posts from banned accounts.

## Sort Strategies
- `latest`: `created_at DESC` (default).
- `trending`: highest engagement in last 48 hours.
  - engagement = `likes_count + (comments_count * 2)`
- `top`: all-time highest `likes_count DESC`.

## Trending Algorithm
- Filter: `created_at >= now()->subHours(48)`.
- Order: `(likes_count + (comments_count * 2)) DESC`.
- This is the only place `orderByRaw` is permitted.
- Implement in `Post::trending()` scope.

## Tabs
- All: `sort=latest`, all types.
- Photos: `type=photo`, `sort=latest`.
- Videos: `type=video`, `sort=latest`.
- Trending: all types, `sort=trending`.
- Top Rated: all types, `sort=top`.

## Guest Experience
- Full Explore access without login.
- Post cards shown without reaction/save actions.
- Show `Log in to react` tooltip on gated reaction action.
- Show `Log in to follow` on user cards.
- Soft CTA banner at top and dismiss via localStorage.

## Search Integration
- Explore search uses `?q=`.
- Searches post body, hashtags, and location.
- Search results span tabs and use relevance-like ordering via latest fallback.
- Search is server-side.

## Pagination
- Default: 24 per page on Explore.
- Photos tab: 30 per page (masonry).
- Other tabs: 20 per page (list).
- Always use `withQueryString()`.
