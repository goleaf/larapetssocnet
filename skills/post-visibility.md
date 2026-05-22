# Post Visibility

Visibility levels:
- `public`
- `followers`
- `private`

Enforce at query level via `Post::scopeVisibleTo()`.
Profile timelines must call `Post::paginateProfileTimeline($profileOwner, $viewer)` from the lazy Posts tab child and let `visibleTo($viewer)` decide public, followers-only, private, account privacy, block, and unavailable-account access. Do not fetch hidden profile posts and filter them in PHP; owner-visible private posts belong in the same published timeline query, while drafts and scheduled posts stay out of the Posts tab.

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
