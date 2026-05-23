# Post Visibility

Visibility levels:
- `public`
- `followers`
- `friends`
- `private`

Enforce at query level via `Post::scopeVisibleTo()`.
Profile timelines must call `Post::paginateProfileTimeline($profileOwner, $viewer)` from the lazy Posts tab child and let `visibleTo($viewer)` decide public, followers-only, friends-only, private, account privacy, block, and unavailable-account access. The profile Posts tab uses cursor-based infinite scroll in 15-post batches, appending IDs returned by `Post::paginateProfileTimeline()` and rehydrating visible rows through `Post::profileTimelinePostsByIds()`; do not switch this back to offset pagination or fetch hidden profile posts and filter them in PHP. The Posts tab media-only mode must pass the media-only flag into those same query helpers so photos/videos are filtered at the database layer through `containingMedia()` while keeping the same visibility rules. The profile Photos tab must page through `Post::profilePhotoMediaPage($profileOwner, $viewer)` in 30-photo batches ordered by `post_media.id`, append only media IDs, and rehydrate with `Post::profilePhotoMediaByIds()` so lightbox navigation never reveals hidden post media and the grid never loads the full photo history up front. The public portfolio route at `/@username/portfolio` must be stricter than the normal owner timeline: only owner-selected posts that pass `Post::visibleTo(null)` may be saved or rendered, so private-account, followers-only, friends-only, scheduled, draft, blocked, or unavailable-owner content cannot leak through a shareable portfolio link. Owners see their non-archived posts, including private and scheduled posts; scheduled posts must render with a distinct scheduled treatment. Non-owners only see published posts allowed by the shared visibility scope.

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
