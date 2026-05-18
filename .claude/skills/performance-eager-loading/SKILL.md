---
name: laravel:performance-eager-loading
description: Prevent N+1 queries by eager loading; enable lazy-loading protection in non-production; choose selective fields
---

# Eager Loading and N+1 Prevention

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.9 guidance on PHP 8.4 with Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

## Load Relations Explicitly

```php
Post::with(['author', 'comments'])->paginate();
```

- Use `load()`/`loadMissing()` after fetching models when needed
- Select only required columns for both base query and relations

## Guard Against Lazy Loading in Dev/Test

Add to a service provider (non-production):

```php
Model::preventLazyLoading(! app()->isProduction());
```

## Verify

- Use a query logger or debugbar to confirm relation queries are minimized
- Add tests that assert counts or avoid unexpected query spikes

