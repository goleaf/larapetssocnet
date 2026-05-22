# Pet Follow

Pet follows use `pet_follows` pivot and are immediate.

## Table
- `follower_id` (user)
- `pet_id`
- `created_at`
- No status column.

## Rules
- No approval flow.
- Cannot follow own pet.
- Toggle behavior mirrors user follow UX.
- No follow notification (noise control).
- Profile pet cards may expose a Follow Pet action only when the viewer is authenticated, is not the pet owner, and SQL `viewer_is_following` is false.
- Profile card follow actions use optimistic Alpine state: immediately set the button to "Following", increment the local follower count, persist through a renderless Livewire action, and roll back on promise rejection.
- Livewire follow actions must authorize through `PetPolicy::follow`, call `PetFollowService`, return the canonical follower count, and skip component re-rendering.

## ORM
- `User` belongsToMany `Pet` via `pet_follows`.
- `Pet` belongsToMany `User` followers via `pet_follows`.

## Counters
- Maintain `pets.followers_count` counter cache.
