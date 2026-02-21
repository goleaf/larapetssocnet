# Pagination Patterns

Laravel pagination best practices for this project.

## Core Rules
- Always use `->paginate(N)` on feed queries.
- Always preserve query string with `->withQueryString()`.
- Never use `simplePaginate` on pages showing total counts.
- Pass paginator to the view and render `->links()`.

## Custom Pagination View
- Publish templates: `php artisan vendor:publish --tag=laravel-pagination`.
- Customize: `resources/views/vendor/pagination/tailwind.blade.php`.
- Style active page emerald and inactive gray.
- Display range label: `Showing 1-15 of 243 posts`.
- Mobile: previous/next only.
- Desktop: page numbers with ellipsis.

## Load More Pattern
- Use a `Load more posts` button linking to `?page=N+1`.
- Full page navigation only (not AJAX).
- Keep URL bookmarkable and JS-optional.
- Show button only when `$posts->hasMorePages()` is true.

## HTMX Future Option
If HTMX is adopted later:
- GET `/feed?page=N`.
- `hx-target="#feed"` and `hx-swap="beforeend"`.
- Not implemented now.
