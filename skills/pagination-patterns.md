# Pagination Patterns

Laravel pagination best practices for this project.

## Core Rules
- Feed uses `cursorPaginate(15)`.
- Most list pages use `paginate($perPage)` when totals are needed.
- Always preserve query string with `->withQueryString()`.
- Avoid `simplePaginate` unless a page explicitly does not need totals.

## UI Components
- Default numbered pagination uses `<x-ui.pagination :paginator="$paginator"/>`.
- Cursor-based feeds render a “next” link using `$posts->nextPageUrl()`.

## Load More Pattern (Feed)
- Cursor pagination only (no numbered pages).
- Show “next” link only when `$posts->nextPageUrl()` exists.
- Keep URL bookmarkable and JS-optional.

## Infinite Scroll Pattern (Profile Posts)
- Use cursor pagination only; never use numeric offset pagination for profile post infinite scroll.
- Load 15 posts per batch ordered by `posts.created_at` and `posts.id`.
- Trigger the next batch from a `wire:intersect.margin.400px` sentinel placed after the last rendered post card.
- Show three fixed-height animated skeleton cards while the Livewire `loadMorePosts` action is in flight.
- Store appended post IDs in Livewire state and re-query visible posts by ID through the shared profile visibility scope before rendering.
