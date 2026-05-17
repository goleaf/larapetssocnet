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
- `status` (`active` or `archived`) and `archived_at`
- `rules`, `location`, `website`
- `species_focus` (default `all`)
- media: avatar + cover
- counters: `members_count`, `posts_count`

## Lifecycle
- Archived groups remain readable to authorized viewers.
- Archived groups reject new posts, comments, reactions, and join requests.
- Only the owner can archive or restore a group through `GroupPolicy`.

## Posts
- Group-context posts use `posts.group_id`.
- Shared posts are tracked in `group_posts` via `Group::sharedPosts()`.

## Services and Policy
- `GroupService`: create/update/delete + membership flows (join/leave/approve/ban/remove).
- `GroupPolicy`: view/post/join/manage/archive/delete rules.
