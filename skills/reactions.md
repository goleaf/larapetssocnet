# Reactions Rules

Reactions follow a polymorphic pattern with one active reaction per user per reactable.

## Supported Types

- `love`
- `cute`
- `funny`
- `wow`
- `sad`
- `support`

## Data and Relationships

- `Reaction` belongs to `user`.
- `Reaction` morphs to `reactable` (`Post` or `Comment`).
- `Post` has `morphMany(Reaction::class, 'reactable')`.
- `Comment` has `morphMany(Reaction::class, 'reactable')`.

## Behavior

- First reaction creates a row and increments `likes_count`.
- Same type again toggles off and decrements `likes_count`.
- Changing type updates existing row only.
- Changing type does not change `likes_count`.

## Notifications

- Send `NewReaction` only on first reaction create.
- Never notify when changing type.
- Never notify self-reactions.

## Querying/UI

- Per-type counts should come from a grouped query or loaded collection.
- Do not issue per-type queries inside Blade loops.
- Viewer reaction state should be loaded in one batch query per page.
