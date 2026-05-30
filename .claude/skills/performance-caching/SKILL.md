---
name: laravel:performance-caching
description: Use framework caches and value/query caching to reduce work; add tags, locks, and explicit invalidation strategies for correctness
---

# Caching Basics

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.12.0 guidance on PHP 8.4+ with Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4.3, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

## Framework caches

```
php artisan optimize
```

Use `php artisan optimize` during production deployments to cache framework files together. Clear generated caches with `php artisan optimize:clear` when needed.

## Values and queries

```php
Cache::remember("post:{$id}", 600, fn () => Post::findOrFail($id));
```

- Choose TTLs based on freshness requirements
- Invalidate explicitly on writes when correctness matters

## Patterns and Strategies

```php
// Stable keys and scopes (e.g., tenant, locale)
Cache::remember("tenant:{$tenantId}:users:index:page:1", now()->addMinutes(5), function () {
    return User::with('team')->paginate(50);
});

// Tags (supported drivers) for grouped invalidation
Cache::tags(['users'])->remember('users.index.page.1', now()->addMinutes(5), fn () => ...);
Cache::tags(['users'])->flush();

// Locks to ensure exclusive expensive work
Cache::lock('reports:daily', 30)->block(5, function () {
    generateReports();
});
```

- Use stable, namespaced keys; include any scoping dimension
- Prefer `remember()` to prevent thundering herds
- Use cache tags only when the active store supports them; provide a plain-key fallback for SQLite/database/local stores that do not
- Avoid caching highly dynamic or user-specific data without a plan
- Document invalidation triggers next to cached code
- Test cache-hit behavior and invalidation after writes or counter updates
