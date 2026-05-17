# Visibility Rules

Post visibility is a three-level access control system and must be enforced at all layers:

1. Query layer
2. Policy layer
3. View layer

If any layer is missing, treat it as a security bug.

## Levels

### `public`
- Visible to authenticated viewers unless another rule blocks access.
- Appears in explore, hashtag pages, and search for authenticated viewers.
- Direct URL requires login first, then applies policy visibility checks.

### `followers`
- Visible only to accepted followers of the author.
- Always visible to post owner, admins, and moderators.
- Never in explore, hashtags, or non-follower search.
- Direct URL denied for non-followers.
- Not overridden by account profile type in the more-open direction.

### `private`
- Visible only to post owner (plus admins/moderators).
- Never appears in feed, explore, hashtags, or search.
- Not accessible to others via direct URL.
- Display with `🔒` badge on own profile.

## Account Privacy Ceiling
- Account privacy is a ceiling.
- Post visibility cannot be broader than account visibility.
- Post visibility can be more restrictive than account visibility.
- Example: `public` post on private account is still follower-only.
