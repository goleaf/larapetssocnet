# Groups

Groups are community spaces.

## Types
- `public`: visible, free join
- `private`: visible, join approval required
- `secret`: hidden, invite-only

## Roles
- `owner` (single)
- `admin`
- `moderator`
- `member`

## Core fields
- name, slug, description
- type, rules, location, website
- cover photo, avatar
- counters: `members_count`, `posts_count`

## Posts
Use nullable `posts.group_id` for group-context posts.

## Services and policy
- `GroupService`: create/update/delete
- `GroupMembershipService`: join/leave/approve/remove/role
- `GroupPolicy`: view/post/manage/delete rules
