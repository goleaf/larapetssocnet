# Counters

Counter cache patterns for Laravel + SQLite.

- Perform counter updates inside `DB::transaction()` for multi-step writes.
- Use model `increment()` / `decrement()` for normal flows.
- Use query builder updates only when avoiding model event/timestamp side effects intentionally.
- Rebuild strategy: centralized `CounterCacheService::rebuild*()` using `withCount()` and `updateQuietly()`.
- Prevent underflow: guard decrements and clamp to zero.
- Test counters with Pest using follow/unfollow/approve/reject scenarios.
- Profile About activity summary must read from `users.posts_count`, `users.post_reactions_received_count`, `users.post_comments_received_count`, and `users.last_post_created_at`; update these during post/comment/reaction writes or via `CounterCacheService::rebuildProfileActivitySummary()`, not with aggregate queries during tab rendering.
