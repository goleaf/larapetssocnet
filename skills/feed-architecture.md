# Feed Architecture

The feed is the most read-heavy page in the app.
Keep the feed query centralized and cursor-paginated.

## Source of Truth
- Query scope: `Post::scopeForFeed(int $viewerId)` in `app/Models/Content/Post.php`.
- Controller: `FeedController@index` in `app/Http/Controllers/Feed/FeedController.php`.
- Stream UI: Livewire `feed.stream` in `resources/views/components/feed/⚡stream.blade.php`.
- Sidebar data: `FeedService::getSidebarData(User $viewer)`.

## Inclusion Rules
- Viewer’s own posts (all visibilities).
- Posts from accepted follows with visibility `public` or `followers`.
- Posts from pets the viewer follows (non-owner) with visibility `public` or `followers`, including both legacy `posts.pet_id` and normalized `pet_post` tags.

## Exclusions
- Unpublished posts (`published()` scope).
- Group posts (`posts.group_id` must be `null`).
- Authors marked `is_banned`.
- Users blocked by or blocking the viewer.

## Ordering & Pagination
- Order by `posts.created_at DESC`, then `posts.id DESC`.
- Use `cursorPaginate(15)` and always chain `->withQueryString()`.
- Feed UI uses the Livewire stream state plus a `wire:intersect.margin.300px` sentinel to append older cursor pages.
- The stream polls for new posts every 30 seconds only while the tab is visible and prepends them after the user taps the new-post indicator.

## Eager Loading & Engagement
- `with(['user', 'user.media', 'pet.media', 'pets', 'media', 'tags'])`.
- `withCount(['likes', 'comments'])`.
- `withExists(['likes as liked_by_viewer' => ...])`.

## Fan-Out Jobs
- `FeedFanOutJob` is idempotent per post. It must acquire `posts:fanout:{postId}`, return immediately when `posts.is_fanned_out` is already true, and set that flag only after collecting the user-follower and pet-follower recipient set.
- Scheduled publication and normal post creation may dispatch fan-out more than once during retries; the `is_fanned_out` flag is the durable guard that prevents duplicate delivery.

## Pinning
- Pinning only affects profile timelines, not feed ordering.
