# Counters

Counter cache patterns for Laravel + SQLite.

- Perform counter updates inside `DB::transaction()` for multi-step writes.
- Use model `increment()` / `decrement()` for normal flows.
- Use query builder updates only when avoiding model event/timestamp side effects intentionally.
- Rebuild strategy: centralized `CounterCacheService::rebuild*()` using `withCount()` and `updateQuietly()`.
- Prevent underflow: guard decrements and clamp to zero.
- Test counters with Pest using follow/unfollow/approve/reject scenarios.
- Profile About activity summary must read from `users.posts_count`, `users.post_reactions_received_count`, `users.post_comments_received_count`, and `users.last_post_created_at`; update these during post/comment/reaction writes or via `CounterCacheService::rebuildProfileActivitySummary()`, not with aggregate queries during tab rendering.
- Post analytics and reaction UI reads from `posts.view_count`, `posts.reactions_count`, `posts.comments_count`, `posts.shares_count`, and the per-type reaction counters (`love_count`, `cute_count`, `funny_count`, `wow_count`, `sad_count`, `support_count`). Keep reaction services and counter rebuild services in sync whenever reaction types change.
- Reaction type switches should update only the old and new per-type counters; total reaction counters change only on first create or toggle-off delete.
- Increment `posts.view_count` only for authenticated non-author feed/profile post-card renders. Other card contexts should pass explicit non-counting contexts so detail pages, discovery, group feeds, and saved collections do not inflate author analytics.
