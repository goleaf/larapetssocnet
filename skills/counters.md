# Counters

Counter cache patterns for Laravel + SQLite.

- Perform counter updates inside `DB::transaction()` for multi-step writes.
- Use model `increment()` / `decrement()` for normal flows.
- Use query builder updates only when avoiding model event/timestamp side effects intentionally.
- Rebuild strategy: centralized `CounterCacheService::rebuild*()` using `withCount()` and `updateQuietly()`.
- Prevent underflow: guard decrements and clamp to zero.
- Test counters with Pest using follow/unfollow/approve/reject scenarios.
