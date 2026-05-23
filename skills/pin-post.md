# Pin Post Rules

Only one pinned post per user.

## Behavior
- Pinning a new post unpins previous pinned post.
- Unpin toggles off for currently pinned post.
- Pinning affects profile display only.
- Pinning does not alter feed/explore ranking.

## Data
- `posts.is_pinned` boolean.
- `posts.pinned_at` timestamp.

## Service
`PinService` owns:
- `pin(User, Post)`
- `unpin(User, Post)`
- `toggle(User, Post)`

Implementation notes:
- enforce ownership (`user_id === post.user_id`)
- use `DB::transaction()` for pinning
- lock the profile owner row before writes so concurrent pin requests serialize per owner
- clear prior pinned posts for that owner before setting the new pinned post
- set the new pinned post via `updateQuietly()`

## Policy
`PostPolicy::pin(User $user, Post $post): bool` restricts to owner.

## Route and Response
- `POST /posts/{post}/pin`
- `DELETE /posts/{post}/pin`
- Controllers return redirects with flash messages (not JSON).

## UI
On profile posts tab:
- show the viewer-visible pinned post in a dedicated top section with an edge-to-edge `Pinned post` banner and secondary-color pin icon
- keep the pinned post card body, media, reactions, comments, and owner menu identical to a regular post card
- keep same post in natural chronological position below
- expose `Pin to profile` / `Unpin from profile` only in the owner-visible post-card three-dot menu
