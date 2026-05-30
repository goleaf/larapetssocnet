# Feed Filtering

Feed supports optional filters via query string parameters.

## Filters
- `?source=people`
- `?source=pets`
- `?type=photo`
- `?type=video`
- `?type=text`
- No `source` means all feed sources.
- No `type` means all posts.

## UI Tabs
- Source tabs: `All | People | Pets`.
- Type tabs: `All types | Photos | Videos | Text`.
- Tabs are Livewire buttons backed by `#[Url]` properties so `source` and `type` still persist in the query string.
- Active tab uses the standard feed tab styling.
- Switching filter tabs resets to page 1 (cursor pagination).
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
- Unknown values are ignored and the feed remains unfiltered for that dimension.
