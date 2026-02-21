# Group Membership

Pivot: `group_members` with composite key (`group_id`, `user_id`).

## Pivot columns
- `role`: `owner|admin|moderator|member`
- `status`: `accepted|pending|banned`
- `invited_by`
- timestamps

## Join logic
- Public group: immediate `accepted`.
- Private group: `pending` until approval.
- Secret group: no open join.

## Leave logic
- Non-owner can leave.
- Owner cannot leave until ownership transfer.

## Ban logic
- `status=banned` blocks rejoin until unbanned.

## Pivot model
Use `Group::using(GroupMember::class)` and helper methods on pivot for approve/promote/demote.
