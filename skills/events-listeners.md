# Events and Listeners

Use events to decouple social actions from side effects.

## Locations
- `app/Events`
- `app/Listeners`

## Block feature events
- `UserBlocked(User $actor, User $target)`
- `UserUnblocked(User $actor, User $target)`

## Listener pattern
One side effect per listener. Keep handlers small and composable.
