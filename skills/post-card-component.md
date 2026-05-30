# Post Card Component

`x-post-card` is the core Blade UI unit shared across feed, profile, explore, search, hashtags, group feeds, and saved posts. The main feed wraps it in the Livewire `posts.card` island so per-card comment toggles, nested menu components, and refreshes do not re-render the whole feed stream.

## Props
- `$post`: eager-loaded `Post` model.
- `$viewer`: optional viewer, defaults to `auth()->user()`.
- `$context`: `feed|profile|explore|detail|group|saved`.

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
- Use `liked_by_viewer`, `current_user_reaction_type`, `saved_by_viewer`, and counter attributes when eager-loaded by feed queries.
- Avoid new per-card database queries for reaction or save state.
- The primary reaction control should keep `data-testid="like-toggle"` for compatibility while rendering the richer configured `paw`, `love`, `haha`, `wow`, `sad`, and `angry` reaction picker, reaction breakdown trigger, and floating burst animation.
- The reaction picker uses Alpine delayed hover, mobile long-press timers, `x-transition` directives, optimistic state, and rollback from the `posts.react` JSON response; keep the picker itself Alpine-owned. The reactions-list modal may be a separate Livewire child component because it owns pagination and follow actions.
- The reaction picker must be keyboard accessible: the trigger exposes `aria-haspopup="listbox"`, Enter/Space opens the picker, reaction options use `role="option"` inside `role="listbox"`, arrow keys move the roving focus, Enter/Space selects, and Escape closes with focus restored.
- Reaction count changes should use Alpine to apply the CSS roll-up or roll-down class and remove it after the keyframe finishes. Do not directly swap numbers without animation.
- The reaction summary emoji stack is supplied by `ReactionSummaryCache` and passed into the reactions modal as rendered HTML. Keep the cache TTL short and invalidate it from the reaction write path when the top-three composition changes.
- The Trending badge is display-only and backed by reaction snapshots; it should not run aggregate reaction queries from the card.
- The Undo toast appears after successful add/change reactions for four seconds and calls the same reaction endpoint with the active type to remove it. Notification suppression belongs to the delayed reaction notification job, not to the browser.
- Use `Post::mediaItemsForDisplay()` when rendering post media or profile media grids so legacy `post_media` rows and Spatie MediaLibrary collections resolve consistently.
- Feed images must keep native `loading="lazy"` and may expose `data-blurhash` plus a low-resolution placeholder data URI from media custom properties when the processing pipeline has provided them.
- Count `posts.view_count` during authenticated non-author `feed` and `profile` renders only. Pass explicit non-counting contexts such as `detail`, `explore`, `group`, or `saved` for cards that should not contribute to author analytics.

## Feed Island Wrapper
- Use `<livewire:posts.card>` inside the `feed.stream` loop with a stable key based on the post ID.
- The wrapper receives the eager-loaded post model and viewer ID, then delegates visual rendering to `x-post-card`.
- Keep heavy per-post interactions inside this child component or existing nested post menu components so the parent feed stream only owns pagination, filters, and polling state.

## Author Analytics
- Render the owner-only chart trigger from `x-post-card`, not page-specific menus.
- Author analytics must authorize through `PostPolicy::viewAnalytics` before loading metric data.
- Keep analytics reads on counter-cache columns for views, reaction totals, comments, and shares. Per-type reaction breakdowns come from the `*_count` reaction columns.
- Estimated reach is an approximation: author follower count plus follower counts for users who reposted the original post.
- Render the comparison chart as server-generated inline SVG through `PostEngagementComparisonSvg`; do not introduce a browser charting dependency.

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

## Viewer Menu
- Authenticated non-owners may see a viewer menu with feed mute actions.
- Muting an author or tagged pet posts to `feed.mutes.store` and must not unfollow that author or pet.
- Include a link to `settings.muted` so users can reverse mutes without finding another post from the source.

## Long Text Preview
- Long post bodies use a CSS line clamp until the reader expands them.
- The See more trigger uses Alpine only: click expands/collapses and desktop hover/focus shows a tooltip containing the full body text that is already in the DOM.
- Do not fetch preview text from the server or navigate away for this hover preview.

## Pinned Highlight
- The top profile pinned post uses the same post card content as regular posts, with only an edge-to-edge `Pinned post` banner prepended above the card body.
- Do not render a floating pinned badge inside chronological duplicates of the same post.

## Time Display
- Use `diffForHumans()` for recent posts (< 7 days).
- Use `format('M j, Y')` for older posts.
- Provide full datetime in `title` attribute.
- Feed timestamps hydrate through the Alpine `relativeTime` helper and refresh once per minute without a server round trip.
- When `edited_at` is set, display `Edited` after the original timestamp with a separator and include the exact edit timestamp in the title tooltip.

## Pet Tags
- Render both the legacy `posts.pet_id` tag and normalized `pet_post` tags as linked badges in the header metadata row.
- Feed queries must eager-load `pet.media` and `pets` so tagged-pet badges do not introduce per-card queries.

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
