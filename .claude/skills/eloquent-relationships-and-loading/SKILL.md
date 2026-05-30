---
name: laravel:eloquent-relationships
description: Define clear relationships and load data efficiently; prevent N+1, use constraints, counts/sums, and pivot syncing safely
---

# Eloquent Relationships and Loading

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.12.0 guidance on PHP 8.5 locally with Composer requiring PHP `^8.4`, Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4.3, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

Model relationships express your domain; load only what you need.

## Commands

```
# Typical loading
Post::with(['author', 'tags'])->withCount('comments')->paginate(20);

# Constrained eager loading
User::with(['posts' => fn($q) => $q->latest()->where('published', true)])->find($id);

# Pivot ops (many-to-many)
$post->tags()->sync([1,2,3]);       // atomic replace
$post->tags()->syncWithoutDetaching([4]);

# Chunking large reads
Order::where('status', 'open')->lazy()->each(fn($o) => ...);
```

## Patterns

- See `laravel:performance-eager-loading` for N+1 detection and measurement
- Use `whereHas()` / `has()` to filter by related existence
- Prefer `withCount`, `withExists`, `withSum`, and `withMax` for aggregates and state flags needed by list views
- Apply global / local scopes for recurring constraints
- Keep relationship names consistent and pluralized where appropriate
- Keep `Model::preventLazyLoading(! app()->isProduction())` enabled in non-production
- Every Eloquent list query must define selected columns, eager loads, pagination, deterministic sorting, and aggregate counts/existence flags
