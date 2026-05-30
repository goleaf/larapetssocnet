# Feed Filtering

Feed supports optional filters via query string parameters.

## Filters
- `?source=people`
- `?source=pets`
- `?type=photo`
- `?type=video`
- `?type=text`
- `?rank=best`
- No `source` means all feed sources.
- No `type` means all posts.
- No `rank` means Latest, unless the user has a stored `feed_ranking_preference` used on first mount.

## UI Tabs
- Source tabs: `All | People | Pets`.
- Type tabs: `All types | Photos | Videos | Text`.
- Ranking tabs: `Latest | Best`.
- Tabs are Livewire buttons backed by `#[Url]` properties so `source`, `type`, and non-default `rank` still persist in the query string.
- Active tab uses the standard feed tab styling.
- Switching filter or ranking tabs resets to page 1 (cursor pagination). Ranking changes also update `users.feed_ranking_preference` and clear the session read-position marker.
- The Latest tab shows a small freshness dot: green for posts in the last hour, yellow for a feed older than one hour but within 24 hours, and grey for no posts or posts older than 24 hours.
- Feed does not expose runtime visual style switchers. Keep it on the single Warm Editorial surface.

## Implementation
- `FeedController@index` validates `source` with `in_array` against `people|pets`.
- `FeedController@index` validates `type` with `in_array` against `text|photo|video`.
- `feed.stream` revalidates the same values because it owns reactive filter changes after the first render.
- The feed view renders `data-feed-surface="warm-editorial"` for the single design standard.
- Source values apply `->forFeedSource($viewerId, $source)` on the base `Post::forFeed($viewerId)` query:
  - `people` keeps the viewer's own posts and posts from accepted followed users.
  - `pets` keeps posts associated with pets followed by the viewer through either `posts.pet_id` or `pet_post`.
- Valid values apply `->byType($type)` on the base `Post::forFeed($viewerId)` query.
- Ranking values apply `->orderForMainFeed($rank)` on the base query:
  - `latest` orders strictly by `created_at` and `id`.
  - `best` uses the database score expression and then falls back to `created_at` and `id`.
- `Post::forFeed()` applies `->distinct()` so followed author plus followed pet matches never duplicate a card.
- `feed_mutes` exclusions happen in the feed scope, not in Blade.
- Unknown values are ignored and the feed remains unfiltered for that dimension.
