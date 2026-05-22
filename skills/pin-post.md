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
- clear prior pinned via `updateQuietly()`
- set new pinned via `updateQuietly()`

## Policy
`PostPolicy::pin(User $user, Post $post): bool` restricts to owner.

## Route and Response
- `POST /posts/{post}/pin`
- `DELETE /posts/{post}/pin`
- Controllers return redirects with flash messages (not JSON).

## UI
On profile posts tab:
- show the viewer-visible pinned post in a dedicated top section with a small `Pinned` label and pin icon badge on the post card
- keep same post in natural chronological position below
