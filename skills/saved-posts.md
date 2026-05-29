# Saved Posts

Saved posts are `user <-> post` many-to-many via `saved_posts`.

## Table
- `id`
- `user_id`
- `post_id`
- `created_at`, `updated_at`
- Unique index on (`user_id`, `post_id`)

## ORM Pattern
- `User::savedPosts()` is `belongsToMany(Post::class, 'saved_posts')`.
- Toggle uses `attach` / `detach` in `SavedPostService::toggle()`.
- Toggle increments/decrements `posts.save_count`.

## Visibility
Saved rows remain even if visibility changes.
Display queries must filter active posts with `Post::visibleTo($viewer)`.
Soft-deleted saved posts must still eager-load with `withTrashed()` and render a "This post has been deleted" placeholder, so users understand why a saved item is unavailable instead of having it silently disappear.

## Saved Page
- Auth-only `/saved`.
- Query owned by `SavedPostsQueryService::paginateForViewer()`.
- Uses `SavedPost::withVisiblePostForViewer($viewer)` and `latest()`.
- Reuse feed post card component.
- Render deleted placeholders for trashed posts and normal post cards only for active posts.
