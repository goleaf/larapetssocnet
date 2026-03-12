# Feed Filtering

Feed supports optional filters via query string parameters.

## Filters
- `?type=photo`
- `?type=video`
- `?type=text`
- No `type` means all posts.

## UI Tabs
- `All | Photos | Videos | Text`.
- Tabs are plain links with `?type=` values.
- Active tab uses the standard feed tab styling.
- Switching filter tabs resets to page 1 (cursor pagination).

## Implementation
- `FeedController@index` validates `type` with `in_array` against `text|photo|video`.
- Valid values apply `->byType($type)` on the base `Post::forFeed($viewerId)` query.
- Unknown values are ignored and the feed remains unfiltered.
