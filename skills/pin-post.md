# Pin Post Rules

Only one pinned post per user.

## Behavior

- Pinning a new post unpins previous pinned post.
- Unpin toggles off for currently pinned post.
- Pinning affects profile display only.
- Pinning does not alter feed/explore ranking.

## Data

- `posts.is_pinned` boolean.

## Service

`PinService` owns:

- `pin(User, Post)`
- `unpin(User, Post)`
- `toggle(User, Post)`

Implementation notes:

- enforce ownership (`user_id === post.user_id`)
- use `DB::transaction()`
- clear prior pinned via `updateQuietly()`
- set new pinned via `updateQuietly()`

## Policy

`PostPolicy::pin(User $user, Post $post): bool` must restrict to owner.

## Route and Response

- `POST /posts/{post}/pin`
- JSON response includes `success` and `is_pinned`

## UI

On profile posts tab:

- show pinned post at top with `Pinned post` marker
- keep same post in natural chronological position below
