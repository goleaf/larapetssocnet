# Relations

Best practices for Eloquent many-to-many self-referencing relationships (`User` follows `User`).

- Use a pivot table with explicit keys (`follower_id`, `following_id`, `status`).
- Define both directions (`followers()`, `following()`).
- Use `withPivot('status')` and timestamps when needed.
- Filter with `wherePivot()` for accepted/pending requests.
- Use `syncWithoutDetaching()` or `upsert()` patterns to avoid duplicates.
- Always enforce unique DB constraint on follower/following pair.
- Use `detach()` for cleanup.
- Update `followers_count` / `following_count` in a transaction.
- SQLite-safe: keep pivot state as plain strings; avoid JSON pivot columns.
