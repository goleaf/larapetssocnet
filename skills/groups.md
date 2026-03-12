# Groups

Groups are community spaces.

## Privacy Types
- `public`: visible, free join
- `private`: visible, join approval required
- `secret`: hidden, invite-only

## Roles
- `owner` (single)
- `admin`
- `moderator`
- `member`

## Core Fields
- `name`, `slug`, `description`
- `privacy` (type mirrors privacy)
- `rules`, `location`, `website`
- `species_focus` (default `all`)
- media: avatar + cover
- counters: `members_count`, `posts_count`

## Posts
- Group-context posts use `posts.group_id`.
- Shared posts are tracked in `group_posts` via `Group::sharedPosts()`.

## Services and Policy
- `GroupService`: create/update/delete + membership flows (join/leave/approve/ban/remove).
- `GroupPolicy`: view/post/manage/delete rules.
