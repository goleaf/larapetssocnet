---
name: laravel:http-client-resilience
description: Use the HTTP client with sensible timeouts, retries, and backoff; capture context and handle failures explicitly
---

# HTTP Client Resilience

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.12.0 guidance on PHP 8.5 locally with Composer requiring PHP `^8.4`, Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4.3, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

Design outbound calls to be predictable and observable.

## Commands

```
use Illuminate\Support\Facades\Http;

$res = Http::baseUrl(config('services.foo.url'))
    ->timeout(5)
    ->retry(3, 200, throw: false)
    ->withHeaders(['Accept' => 'application/json'])
    ->get('/v1/things', ['q' => 'bar']);

if (!$res->successful()) {
    Log::warning('foo api failure', [
        'status' => $res->status(),
        'body' => substr($res->body(), 0, 500),
    ]);
}
```

## Patterns

- Set timeouts explicitly (client and server defaults differ)
- Use limited retries with backoff for transient failures only
- Prefer dependency injection for testability
- Add request/response context to logs (redact sensitive data)
- Use `pool()` for concurrency when calling multiple endpoints

