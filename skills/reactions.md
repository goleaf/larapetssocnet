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
- Changing type updates existing row only (no counter change).
- `LikeController` uses `ToggleReactionAction` with `love`.
- `ReactionController` accepts explicit type.

## Comment Reactions
- `CommentService::toggleReaction()` delegates to `HasReactions::toggleReaction()`.
- Comment reactions update `comments.reactions_count` via the trait.
- No notification side effects for comment reactions in current code.

## Notifications
- `NewReaction` is sent only on first post reaction create.
- Never notify on type change.
- Never notify self-reactions.

## Querying/UI
- Per-type counts should come from a grouped query or loaded collection.
- Do not issue per-type queries inside Blade loops.
- Viewer reaction state should be loaded in one batch query per page.
