---
name: laravel:api-resources-and-pagination
description: Use API Resources with pagination and conditional fields; keep response shapes stable and cache-friendly
---

# API Resources and Pagination

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.12.0 guidance on PHP 8.4+ with Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4.3, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

Represent models via Resources; keep transport concerns out of Eloquent.

## Commands

```
# Resource
sail artisan make:resource PostResource    # or: php artisan make:resource PostResource

# Controller usage
return PostResource::collection(
    Post::with('author')->latest()->paginate(20)
);

# Resource class
public function toArray($request)
{
    return [
        'id' => $this->id,
        'title' => $this->title,
        'author' => new UserResource($this->whenLoaded('author')),
        'published_at' => optional($this->published_at)->toAtomString(),
    ];
}
```

## Patterns

- Prefer `Resource::collection($query->paginate())` over manual arrays
- Use `when()` / `mergeWhen()` for conditional fields
- Keep pagination cursors/links intact for clients
- Version resources when contracts change; avoid breaking fields silently

