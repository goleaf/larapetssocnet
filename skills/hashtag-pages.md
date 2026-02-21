# Hashtag Pages

Route pattern:

- `GET /hashtags/{slug}`

## Visibility

- Show only publicly explorable posts.
- Guest accessible.

## Page Content

- Header title: `#tag` and post count.
- Sort options: `latest` (default), `trending`, `top`.
- Pagination: `20` posts per page.
- Related hashtags: top 6 by co-occurrence.

## Related Hashtag Query

Compute related tags from shared post IDs:

- get posts for current hashtag
- query other hashtags attached to those posts
- order by co-occurrence desc

## SEO

Include:

- canonical URL
- Open Graph metadata
- description meta

`HashtagController` handles rendering.
`Hashtag::scopeBySlug($query, string $slug)` supports lookup.
