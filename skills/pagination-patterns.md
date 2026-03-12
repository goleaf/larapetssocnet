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
