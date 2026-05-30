---
name: laravel:rate-limiting
description: Apply per-user and per-route limits with RateLimiter and throttle middleware; use backoffs and headers for clients
---

# Rate Limiting and Throttle

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.12.0 guidance on PHP 8.4+ with Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4.3, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

Protect endpoints from abuse while keeping UX predictable.

## Commands

```
// App\Providers\RouteServiceProvider
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
});

// routes/api.php
Route::middleware(['throttle:api'])->group(function () {
    // ...
});
```

## Patterns

- Scope limits by user when authenticated; fall back to IP
- Communicate limits to clients via standard headers
- Provide sensible 429 responses with retry hints
- Separate bursty endpoints into specialized limiters

