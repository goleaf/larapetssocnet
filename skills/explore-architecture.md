# Explore Architecture

Explore is the public discovery surface.
It is accessible to guests and authenticated users.
No login is required to browse Explore.

## What Explore Shows
- Public, published posts only.
- Posts from non-private, non-banned authors.
- For authenticated users, exclude posts from blocked users (both directions).

## Query Source of Truth
- Controller: `ExploreController@index` in `app/Http/Controllers/ExploreController.php`.
- Query builder: `Post::paginateExploreResults($viewer, $type, $search, $perPage = 15)`.
- Visibility filter: `Post::scopeExplorable(?User $viewer)`.

## Tabs / Types
- `all` (default)
- `photos`
- `videos`
- `trending`

## Ordering
- Default: `latest('posts.created_at')`.
- Trending: `orderByDesc('likes_count')`, then `orderByDesc('comments_count')`, then `latest('posts.created_at')`.

## Search Integration
- Query param: `?q=`.
- Uses `Post::scopeExploreSearch($term)`.
- Matches post body, location, hashtags, and author name/username.

## Eager Loading
- `with(['user.media', 'author.media', 'hashtags', 'pet' => fn ($q) => $q->visibleTo($viewer)])`.

## Pagination
- Default: 15 per page.
- Always call `->withQueryString()`.
