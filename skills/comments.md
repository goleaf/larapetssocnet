# Comments Rules

Comments are threaded with a maximum of three visual levels.

## Thread Model
- Top-level comment: `parent_id = null`.
- Direct reply: `parent_id = top-level comment id`.
- Reply-to-reply: `parent_id = direct reply id`.
- Replies beyond the third visual level are flattened onto the third-level parent in `CommentService` so the tree does not become visually unbounded.

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
- Inline feed previews show the three most recent top-level comments, with the two most recent replies under each visible parent.
- Full post pages default to oldest-first reading order and can switch to Top or Newest through the Livewire thread sort control.
- Expanded reply threads load replies oldest first.

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
- Max body length: `CommentService::MAX_BODY_LENGTH` (500).

`CommentService` owns business logic.
`CommentPolicy` owns authorization.

## Livewire Thread Surface
- Feed post cards mount `posts.comments-thread` only when the comments panel is opened.
- Inline feed panels use `CommentService::previewThread()` and full post pages mount the same component with `fullPage=true`, using `CommentService::threadForPost()`.
- The component may poll while visible through a lightweight activity fingerprint from `CommentService::threadActivity()`.
- Top-level comments, replies, edits, inline deletes, pin/unpin actions, reports, commenter blocks, mention suggestions, and emoji insertion submit through Livewire-backed shared actions so the user remains inside the post context.
