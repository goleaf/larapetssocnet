# Reactions Rules

Reactions are polymorphic with one active reaction per user per reactable.

## Supported Types
- `paw`
- `love`
- `haha`
- `wow`
- `sad`
- `angry`

Aliases:
- `like`, `cute`, and `support` normalize to `paw`.
- `care` normalizes to `love`.
- `laugh` and `funny` normalize to `haha`.

## Data and Relationships
- `Reaction` belongs to `user`.
- `Reaction` morphs to `reactable` (`Post` or `Comment`).
- `Post` and `Comment` use the `HasReactions` trait.

## Post Reactions (ReactionService)
- First reaction creates a row and increments `posts.likes_count` and `posts.reactions_count`.
- Same type again toggles off and decrements both counters.
- Changing type updates the existing row, keeps the total counters unchanged, decrements the old per-type counter, and increments the new per-type counter.
- Reaction toggles must capture the pre/post top-three reaction composition and invalidate the short-lived reaction-summary HTML cache only when that top composition changes.
- Reaction toggles record a per-minute `post_reaction_snapshots` row so velocity checks can compare the current count against the count from five minutes ago.
- `LikeController` uses `ToggleReactionAction` with the configured default `paw`.
- `ReactionController` accepts explicit type.
- Both endpoints should return the total count, current viewer reaction, and per-type counter cache values so Alpine can reconcile optimistic state from the database response.

## Comment Reactions
- `CommentService::toggleReaction()` delegates to `HasReactions::toggleReaction()`.
- Comment reactions are limited to `paw` and `love` and update `comments.reactions_count`, `comments.paw_count`, and `comments.love_count` via the trait.
- No notification side effects for comment reactions in current code.

## Notifications
- `NewReaction` is queued through `SendReactionNotificationJob` only on first post reaction create and delayed by four seconds to support the UI undo window.
- The delayed job must re-check that the same reaction row still exists before notifying; an undone reaction is a no-op.
- Never notify on type change.
- Never notify self-reactions.
- Daily heavy-reactor summaries are opt-in through `notification_preferences.daily_reaction_summary` and dispatched by `reactions:send-daily-summaries` at 8pm in the user's timezone.

## Querying/UI
- Shared post cards render the authenticated reaction picker from one `postCard(...)` Alpine state object, using `reactionOptions`, `current_user_reaction_type`, and the `posts.react` endpoint.
- Keep the legacy `posts.like` endpoint as a default-paw compatibility path for older surfaces.
- Per-type counts should come from counter cache columns, a grouped query, or loaded collection.
- The reaction breakdown emoji stack should render through `ReactionSummaryCache` with a 60-second TTL instead of rebuilding markup in every feed-card render.
- Count changes should animate through Alpine-toggled CSS roll-up / roll-down classes and reset on `animationend`.
- The picker must remain keyboard-operable: Enter/Space opens, arrows move between emoji options, Enter selects, and Escape closes while restoring focus to the trigger.
- The post card may show the subtle Trending badge when `ReactionVelocityService` detects more than 10 new reactions per minute over the five-minute snapshot window.
- After an add/change reaction succeeds, the card shows a four-second Undo toast. Undo sends a normal remove request and suppresses author notification because the delayed job re-validates the row.
- Do not issue per-type queries inside Blade loops.
- Viewer reaction state should be loaded by list queries with `Post::withCurrentViewerReaction()` or an equivalent batch/subselect, never by calling the `current_user_reaction` accessor in a Blade loop.
