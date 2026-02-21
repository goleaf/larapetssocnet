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

## ORM
- `User` belongsToMany `Pet` via `pet_follows`.
- `Pet` belongsToMany `User` followers via `pet_follows`.

## Counters
- Maintain `pets.followers_count` counter cache.
