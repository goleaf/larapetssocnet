# Reactions Rules

Reactions are polymorphic with one active reaction per user per reactable.

## Supported Types
- `love`
- `cute`
- `funny`
- `wow`
- `sad`
- `support`

Aliases:
- `like` normalizes to `love`.
- `laugh` normalizes to `funny`.

## Data and Relationships
- `Reaction` belongs to `user`.
- `Reaction` morphs to `reactable` (`Post` or `Comment`).
- `Post` and `Comment` use the `HasReactions` trait.

## Post Reactions (ReactionService)
- First reaction creates a row and increments `posts.likes_count` and `posts.reactions_count`.
- Same type again toggles off and decrements both counters.
- Changing type updates the existing row, keeps the total counters unchanged, decrements the old per-type counter, and increments the new per-type counter.
- `LikeController` uses `ToggleReactionAction` with `love`.
- `ReactionController` accepts explicit type.
- Both endpoints should return the total count, current viewer reaction, and per-type counter cache values so Alpine can reconcile optimistic state from the database response.

## Comment Reactions
- `CommentService::toggleReaction()` delegates to `HasReactions::toggleReaction()`.
- Comment reactions update `comments.reactions_count` via the trait.
- No notification side effects for comment reactions in current code.

## Notifications
- `NewReaction` is sent only on first post reaction create.
- Never notify on type change.
- Never notify self-reactions.

## Querying/UI
- Shared post cards render the authenticated reaction picker from one `postCard(...)` Alpine state object, using `reactionOptions`, `current_user_reaction_type`, and the `posts.react` endpoint.
- Keep the legacy `posts.like` endpoint as a love-toggle compatibility path for older surfaces.
- Per-type counts should come from counter cache columns, a grouped query, or loaded collection.
- Do not issue per-type queries inside Blade loops.
- Viewer reaction state should be loaded by list queries with `Post::withCurrentViewerReaction()` or an equivalent batch/subselect, never by calling the `current_user_reaction` accessor in a Blade loop.
