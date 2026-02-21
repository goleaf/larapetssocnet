# Post Card Component

`x-post-card` is the core UI unit shared across feed, profile, explore, search, hashtags, group feeds, and saved posts.

## Props
- `$post`: eager-loaded `Post` model.
- `$myReactions`: keyed collection by post id.
- `$mySaved`: collection of saved post ids.
- `$showComments`: bool, default false.
- `$compact`: bool, default false.
- `$context`: `feed|profile|explore`.

## Sections Order
1. Pin banner (only pinned on profile context).
2. Header (avatar, name, time, options).
3. Body text with read-more.
4. Media (photo grid or video player).
5. Location badge.
6. Hashtag chips.
7. Reaction bar.
8. Action row (comment, save, share).
9. Comment preview when enabled.

## Reaction State
- Read from `$myReactions->get($post->id)?->type`.
- Pass to Alpine as `currentReaction`.
- No DB queries in component.

## Save State
- Read from `$mySaved->has($post->id)`.
- Pass to Alpine as `saved` boolean.
- No DB queries in component.

## Time Display
- Use `diffForHumans()` for recent posts (< 7 days).
- Use `format('M j, Y')` for older posts.
- Provide full datetime in `title` attribute.
