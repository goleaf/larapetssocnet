---
name: laravel:custom-helpers
description: Create and register small, pure helper functions when they improve clarity; keep them organized and tested
---

# Custom Helpers

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.9 guidance on PHP 8.4 with Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

## Create a helper file

```php
// app/Support/helpers.php
function money(int $cents): string { return number_format($cents / 100, 2); }
```

## Autoload

Add to `composer.json`:

```json
{
  "autoload": { "files": ["app/Support/helpers.php"] }
}
```

Run `composer dump-autoload`.

## Guidelines

- Keep helpers small and pure; avoid hidden IO/state
- Prefer static methods on value objects when domain-specific

