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
- `gif_url`, `gif_preview_url`, `gif_title`, `gif_provider`
- `language_code`, `quality_score`
- `deleted_at`, timestamps

Comment-adjacent tables:
- `comment_drafts`: one draft per `(user_id, post_id)` storing draft text and optional GIF payload.
- `comment_thread_subscriptions`: active thread notification subscriptions keyed by `(user_id, root_comment_id)`.
- `comment_translations`: cached translations keyed by `(comment_id, target_language)`.

## Tombstone Soft Delete
- Keep row for thread integrity.
- Replace content with `[comment removed]` in both `body` and `body_html`.
- Do not break reply chains.

## Ordering
- Inline feed previews show three quality-weighted top-level comments, with the two most recent replies under each visible parent.
- Full post pages default to oldest-first reading order and can switch to Top or Newest through the Livewire thread sort control. Top sorts by `quality_score`, then reaction count, then recency.
- Expanded reply threads load replies oldest first.

## Counters
- `posts.comments_count` includes top-level + replies.
- Livewire thread surfaces refresh their local counter from `posts.comments_count` and dispatch `post-card-refresh` after create, reply, delete, or visible polling updates.
- Deleted-comment decrements for `posts.comments_count` are finalized by a queued job dispatched from `CommentObserver::deleted()` after the soft delete is confirmed.

## Content Pipeline
Comment body processing mirrors posts:
- sanitize and normalize in `ContentService`
- mention extraction via `MentionService`

## Notifications
- Top-level comment notifies post author (except self).
- Reply notifies parent-comment author (except self).
- Mentions in comments are extracted from raw body text during comment creation, then resolved and notified by queued `comments` batch jobs. The dispatcher must batch recipient block, follow, role, and pet/post visibility checks before fan-out instead of calling policy-adjacent relationship methods once per mentioned user. Mention fan-out jobs are unique by comment, and per-recipient mention notification jobs must keep unique IDs plus a database-notification idempotency check so burst dispatches cannot create duplicate visible notifications.
- Users who post at least two comments in the same root thread are automatically subscribed from `CommentObserver` to later thread replies unless they unsubscribe.

## Access Rules
- Guests cannot access post comment pages or create comments.
- Authenticated viewers can read comments when they can view the parent post.
- Max body length: `CommentService::MAX_BODY_LENGTH` (500).

`CommentService` owns business logic.
`CommentPolicy` owns authorization.

## Livewire Thread Surface
- Feed post cards mount `posts.comments-thread` only when the comments panel is opened.
- Individual comments render through recursive Blade partials; do not create one Livewire component per comment.
- Inline feed panels use `CommentService::previewThread()` and full post pages mount the same component with `fullPage=true`, using `CommentService::threadForPost()`.
- The component may poll while visible through a lightweight activity fingerprint from `CommentService::threadActivity()`.
- Top-level comments, replies, edits, inline deletes, pin/unpin actions, reports, commenter blocks, mention suggestions, and emoji insertion submit through Livewire-backed shared actions so the user remains inside the post context.
- Paw/Love comment reactions inside Livewire threads call the parent `posts.comments-thread` action and reconcile Alpine optimistic state from the returned counters.
- The top-level composer autosaves a draft every 10 seconds when text or a GIF is present, restores the draft on mount, and clears it on submit or explicit discard.
- Full post pages with more than 50 comments expose a debounced search field that queries within the post and highlights matched text in the rendered comment body.
- GIF search is server-side only so provider keys stay in configuration. The Livewire component stores only selected GIF metadata on the comment.
- Inline translation calls the server-side translation service and caches results in `comment_translations`; the original text remains visible.
