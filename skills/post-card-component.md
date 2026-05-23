# Post Card Component

`x-post-card` is the core UI unit shared across feed, profile, explore, search, hashtags, group feeds, and saved posts.

## Props
- `$post`: eager-loaded `Post` model.
- `$viewer`: optional viewer, defaults to `auth()->user()`.
- `$context`: `feed|profile|explore`.

## Sections Order
1. Pinned badge (only pinned on profile context).
2. Header (avatar, name, time, options).
3. Body text with read-more.
4. Media (photo grid or video player).
5. Location badge.
6. Hashtag chips.
7. Action row (like, comments, save, share, report when available).

## Alpine State
- Pass card state through `x-data="postCard(...)"` using `Illuminate\Support\Js::from()`.
- Do not place raw `@js(...)` directives inside component attribute strings; they can render literally and break Alpine.
- Keep action labels server-rendered or Alpine-backed so buttons never appear as blank squares before hydration.

## Engagement State
- Use `liked_by_viewer`, `saved_by_viewer`, and counter attributes when eager-loaded by feed queries.
- Avoid new per-card database queries for reaction or save state.
- Use `Post::mediaItemsForDisplay()` when rendering post media or profile media grids so legacy `post_media` rows and Spatie MediaLibrary collections resolve consistently.

## Owner Profile Menu
- In profile context, render the owner-only three-dot menu from inside `x-post-card`.
- Keep profile pinning actions in that menu as `Pin to profile` / `Unpin from profile`; do not add separate inline pin buttons below profile posts.
- Non-owners and non-profile contexts must not render owner post actions.

## Time Display
- Use `diffForHumans()` for recent posts (< 7 days).
- Use `format('M j, Y')` for older posts.
- Provide full datetime in `title` attribute.

## Explore Card Variants
`x-post-card` with `context="explore"`:
- Compact header (smaller spacing and avatar).
- Body truncated to 200 chars.
- Full media shown.
- Reaction area uses authenticated viewer state.
- No quick-comment section.

`x-explore-photo-card`:
- Image-only masonry card.
- Hover overlay with author and counts.
- Click navigates to post page.
