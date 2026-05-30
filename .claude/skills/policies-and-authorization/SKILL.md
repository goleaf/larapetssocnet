---
name: laravel:policies-and-authorization
description: Enforce access via Policies and Gates; use authorize() and authorizeResource() to standardize controller protections
---

# Policies and Authorization

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.12.0 guidance on PHP 8.5 locally with Composer requiring PHP `^8.4`, Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4.3, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

Use Policies for per-model actions; use Gates for cross-cutting checks.

## Commands

```
# Generate a policy
sail artisan make:policy PostPolicy --model=Post   # or: php artisan make:policy PostPolicy --model=Post

# Apply in routes (resource controllers)
Route::resource('posts', PostController::class);
// In controller constructor
$this->authorizeResource(Post::class, 'post');

# One-off checks
$this->authorize('update', $post);           // in controller
Gate::allows('manage-billing', $user);       // ad-hoc gate
```

## Patterns

- Use resource policy methods: `viewAny, view, create, update, delete, restore, forceDelete`
- Prefer policy methods over inline checks; keeps controllers clean
- Register policies in `AuthServiceProvider`
- Use `can` middleware for quick route protection: `->middleware('can:update,post')`
- In tests, assert `actingAs($user)->get(...)->assertForbidden()` for denied cases

