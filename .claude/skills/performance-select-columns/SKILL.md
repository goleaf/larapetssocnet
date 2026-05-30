---
name: laravel:performance-select-columns
description: Select only required columns to reduce memory and transfer costs; apply to base queries and relations
---

# Select Only Needed Columns

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.12.0 guidance on PHP 8.4+ with Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4.3, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

Reduce payloads by selecting exact fields:

```php
User::select(['id', 'name'])->paginate();

Post::with(['author:id,name'])->select(['id','author_id','title'])->get();
```

- Avoid `*`; keep DTOs/resources aligned with selected fields
- Combine with eager loading to avoid N+1
- Always include primary keys and foreign keys needed to hydrate Eloquent relationships
- In Livewire components, select only the fields that are rendered or required by follow-up actions, then store IDs or compact arrays in public state instead of graph-loaded models
