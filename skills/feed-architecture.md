# Feed Architecture

The feed is the most read-heavy page in the app.
Keep the feed query centralized and cursor-paginated.

## Source of Truth
- Query scope: `Post::scopeForFeed(int $viewerId)` in `app/Models/Post.php`.
- Controller: `FeedController@index` in `app/Http/Controllers/FeedController.php`.
- Sidebar data: `FeedService::getSidebarData(User $viewer)`.

## Inclusion Rules
- Viewer’s own posts (all visibilities).
- Posts from accepted follows with visibility `public` or `followers`.
- Posts from pets the viewer follows (non-owner) with visibility `public` or `followers`.

## Exclusions
- Unpublished posts (`published()` scope).
- Group posts (`posts.group_id` must be `null`).
- Authors marked `is_banned`.
- Users blocked by or blocking the viewer.

## Ordering & Pagination
- Order by `posts.created_at DESC`, then `posts.id DESC`.
- Use `cursorPaginate(15)` and always chain `->withQueryString()`.
- Feed UI uses a “next” cursor link rather than numbered pages.

## Eager Loading & Engagement
- `with(['user', 'author', 'author.media', 'pet', 'media', 'tags'])`.
- `withCount(['likes', 'comments'])`.
- `withExists(['likes as liked_by_viewer' => ...])`.

## Pinning
- Pinning only affects profile timelines, not feed ordering.
