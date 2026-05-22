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
- Treat messaging as a mutual-follow capability for normal users; profile buttons, message policies, and contact flows must all agree on the same bidirectional relationship check.
- Resolve profile mutual connections as an accepted-status database intersection between the viewer's following rows and the profile owner's follower rows; do not load both follower lists and compare them in PHP.
- SQLite-safe: keep pivot state as plain strings; avoid JSON pivot columns.
