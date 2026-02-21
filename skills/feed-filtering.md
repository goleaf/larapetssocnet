# Feed Filtering

Feed supports optional filters via query string parameters.

## Filters
- `?type=photo`: photo posts only.
- `?type=video`: video posts only.
- `?type=text`: text posts only.
- No `type`: all posts.

## UI Tabs
- `All | Photos | Videos | Text`.
- Tabs are plain links with `?type=` values.
- Active tab uses Tailwind `border-b-2` highlight.
- Filters apply on top of base feed query.
- Switching filter tabs resets to page 1.

## Implementation
- `FeedService::getFeed()` accepts optional `?string $type`.
- If type is valid, chain `->byType($type)`.
- Controller validates against `text|photo|video`.
- Unknown values are ignored and feed remains unfiltered.
