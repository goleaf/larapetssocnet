---
name: laravel:performance-eager-loading
description: Prevent N+1 queries by eager loading; enable lazy-loading protection in non-production; choose selective fields
---

# Eager Loading and N+1 Prevention

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.12.0 guidance on PHP 8.4+ with Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4.3, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

## Load Relations Explicitly

```php
Post::query()
    ->select(['id', 'author_id', 'title', 'created_at'])
    ->with(['author:id,name', 'comments:id,post_id'])
    ->withCount('comments')
    ->latest()
    ->paginate();
```

- Every list query must declare selected columns, eager loads, pagination, sorting, and aggregate counts/existence flags before rendering
- Use `load()`/`loadMissing()` after fetching models when needed
- Select only required columns for both base query and relations
- Include the parent key and every foreign key required by Eloquent when selecting relation columns
- Use `withExists()`, `withCount()`, and batched lookup maps instead of per-row `exists()` or `count()` calls
- Treat Laravel automatic eager loading as beta; critical surfaces still require explicit eager loads

## Guard Against Lazy Loading in Dev/Test

Add to a service provider (non-production):

```php
Model::preventLazyLoading(! app()->isProduction());
```

## Verify

- Use a query logger or debugbar to confirm relation queries are minimized
- Add tests that assert counts or avoid unexpected query spikes
- Keep `Model::preventLazyLoading(! app()->isProduction())` enabled unless a documented and tested exception exists
