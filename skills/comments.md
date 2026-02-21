# Comments Rules

Comments are threaded with max depth of two levels.

## Thread Model

- Top-level comment: `parent_id = null`.
- Reply: `parent_id = top-level comment id`.
- Replies cannot have replies.

## Data Shape

Comments table fields:

- `id`, `post_id`, `user_id`, `parent_id`
- `body`, `body_html`
- `likes_count`
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

## Content Pipeline

Comment body processing mirrors posts:

- purify
- markdown
- mention handling
- hashtag handling

## Notifications

- Top-level comment notifies post author (except self).
- Reply notifies parent-comment author (except self).
- Mentions in comments notify mentioned users (except self).

## Access Rules

- Guests can read comments.
- Guests cannot create comments.
- Max body length: 1000 chars.

`CommentService` owns business logic.
`CommentPolicy` owns authorization.
