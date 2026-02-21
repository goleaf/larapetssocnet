# Saved Posts

Saved posts are `user <-> post` many-to-many via `saved_posts`.

## Table

- `user_id`
- `post_id`
- `created_at`
- Composite primary key (`user_id`, `post_id`)

## ORM Pattern

- `User::savedPosts()` is `belongsToMany(Post::class, 'saved_posts')`.
- Toggle uses `attach`/`detach`.
- `isSaved` uses `exists()`.
- No raw `DB::table` operations for save/unsave flows.

## Visibility

Saved rows remain even if visibility changes.
Display queries must filter with `visibleTo($viewer)`.

## Saved Page

- Auth-only `/saved`.
- Reverse save order (`pivot created_at desc`).
- Reuse feed post card component.

`SavedPostService` owns `toggle`, `isSaved`, `getSaved`.
