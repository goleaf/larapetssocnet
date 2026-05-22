# Post Visibility

Visibility levels:
- `public`
- `followers`
- `friends`
- `private`

Enforce at query level via `Post::scopeVisibleTo()`.
Profile timelines must call `Post::paginateProfileTimeline($profileOwner, $viewer)` from the lazy Posts tab child and let `visibleTo($viewer)` decide public, followers-only, friends-only, private, account privacy, block, and unavailable-account access. The profile Posts tab uses cursor-based infinite scroll in 15-post batches, appending IDs returned by `Post::paginateProfileTimeline()` and rehydrating visible rows through `Post::profileTimelinePostsByIds()`; do not switch this back to offset pagination or fetch hidden profile posts and filter them in PHP. Owners see their non-archived posts, including private and scheduled posts; scheduled posts must render with a distinct scheduled treatment. Non-owners only see published posts allowed by the shared visibility scope.

## Visibility Change Rules On Edit
- Post owners can always change visibility.
- No hard restrictions by direction.
- Downgrade with engagement should show a warning.
- Changing to private does not delete reactions/comments; data is preserved.
- Restoring visibility shows prior interactions again.

## Visibility And Counter Caches
- `likes_count` and `comments_count` always reflect real totals.
- Changing visibility does not reset counters.
- For restricted visibilities, counts are intended for owner-facing contexts.

## Visibility And Notifications
- Reactions/comments on followers/private posts still notify post author.
- Followers/private content does not fan out discovery-style notifications to non-viewers.
