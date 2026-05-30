# Comments Rules

Comments are threaded with max depth of two levels.

## Thread Model
- Top-level comment: `parent_id = null`.
- Reply: `parent_id = top-level comment id`.
- Replies cannot have replies (enforced in `CommentService`).

## Data Shape
Comments table fields include:
- `id`, `post_id`, `user_id`, `parent_id`
- `body`, `body_html`, `edited_at`
- `replies_count`, `reactions_count`
- `deleted_at`, timestamps

## Tombstone Soft Delete
- Keep row for thread integrity.
- Replace content with `[comment removed]` in both `body` and `body_html`.
- Do not break reply chains.

## Ordering
- Top-level comments: oldest first.
- Replies under each parent: oldest first.

## Counters
- `posts.comments_count` includes top-level + replies.
- Livewire thread surfaces refresh their local counter from `posts.comments_count` and dispatch `post-card-refresh` after create, reply, delete, or visible polling updates.

## Content Pipeline
Comment body processing mirrors posts:
- sanitize and normalize in `ContentService`
- mention extraction via `MentionService`

## Notifications
- Top-level comment notifies post author (except self).
- Reply notifies parent-comment author (except self).
- Mentions in comments notify mentioned users (except self).

## Access Rules
- Guests cannot access post comment pages or create comments.
- Authenticated viewers can read comments when they can view the parent post.
- Max body length: `CommentService::MAX_BODY_LENGTH` (1000).

`CommentService` owns business logic.
`CommentPolicy` owns authorization.

## Livewire Thread Surface
- Feed post cards mount `posts.comments-thread` only when the comments panel is opened.
- The component must use `CommentService::previewThread()` so inline feed comments and full post pages share tombstone, reply-depth, reaction-summary, and visibility behavior.
- The component may poll while visible through a lightweight activity fingerprint from `CommentService::threadActivity()`.
- Reply forms inside this component submit through Livewire, but full post pages can continue using the HTTP routes backed by the same actions.
