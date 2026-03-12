# Group Membership

Membership is stored in the `group_members` table and modeled by `GroupMember` (pivot-like model with `id`).

## Columns
- `group_id`, `user_id` (unique pair)
- `role`: `owner|admin|moderator|member`
- `status`: `active|accepted|pending|rejected|removed|banned`
- `joined_at` timestamp
- `invited_by` nullable user id
- timestamps

## Join Logic (GroupService)
- Public group: immediate `active`.
- Private group: `pending` until approval.
- Secret group: no open join.
- Rejoin after rejection: blocked for 7 days.

## Leave Logic
- Non-owner can leave.
- Owner cannot leave until ownership transfer.

## Ban Logic
- `status=banned` blocks rejoin until unbanned.

## Queries
- `GroupMember::paginateActiveForGroup($group, $perPage = 20)`
- `GroupMember::pendingForGroup($group)`
