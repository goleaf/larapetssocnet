# Post Card Component

`x-post-card` is the core UI unit shared across feed, profile, explore, search, hashtags, group feeds, and saved posts.

## Props
- `$post`: eager-loaded `Post` model.
- `$viewer`: optional viewer, defaults to `auth()->user()`.
- `$context`: `feed|profile|explore`.

## Sections Order
1. Pinned banner (only the dedicated profile pinned-highlight instance).
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

## Share Menu
- Render sharing through the reusable Livewire `posts.share-menu` in the action row, not through page-local JavaScript.
- Keep the three actions in this order: Repost, Quote post, Copy link.
- Use a mobile bottom sheet and desktop popover. If the menu is rendered inside an overflow-hidden card, teleport the overlay to `body` and position the desktop popover from the trigger rectangle.
- Repost should create a new post referencing `original_post_id` and update the displayed share count. Quote post should open the shared composer in modal mode with the original post preview. Copy link should use the clipboard fallback pattern and show a short copied tooltip.

## Owner Menu
- Render owner-only post actions from inside the `x-post-card` three-dot menu for every post-card context.
- Keep `Edit post` as the first menu item while the post is inside the 24-hour edit window; after the window, render the disabled explanatory item instead of an edit action.
- Keep profile pinning actions in that menu as `Pin to profile` / `Unpin from profile` only for profile-context cards; do not add separate inline pin buttons below profile posts.
- Keep `Delete post` behind the reusable Livewire delete trigger. It must open a confirmation modal with a short post preview, dispatch `post-delete-requested` for optimistic card removal, and enqueue `DeletePostCascadeJob` instead of using a browser confirm or blocking form submission.
- Non-owners must not render owner post actions.

## Pinned Highlight
- The top profile pinned post uses the same post card content as regular posts, with only an edge-to-edge `Pinned post` banner prepended above the card body.
- Do not render a floating pinned badge inside chronological duplicates of the same post.

## Time Display
- Use `diffForHumans()` for recent posts (< 7 days).
- Use `format('M j, Y')` for older posts.
- Provide full datetime in `title` attribute.
- When `edited_at` is set, display `Edited` after the original timestamp with a separator and include the exact edit timestamp in the title tooltip.

## Embedded Context
- Quote posts and reposts should render an embedded original-post block everywhere `x-post-card` appears.
- The block should show whether it is a quote or repost, the original author's avatar/name, up to three lines of original text, and the first media thumbnail when present.

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
