---
name: laravel:dependencies-trim-packages
description: Remove unneeded Composer packages and assets to improve boot time, memory, and security surface
---

# Trim Dependencies

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.9 guidance on PHP 8.4 with Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

- Audit packages: `composer show --tree` and remove unused ones
- Prefer first-party or built-in features before adding new packages
- Regularly update; pin major versions via constraints and test

```
composer remove vendor/package
```

