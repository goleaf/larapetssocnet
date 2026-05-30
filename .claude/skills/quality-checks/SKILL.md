---
name: laravel:quality-checks
description: Unified quality gates for Laravel projects; Pint, static analysis (PHPStan/Psalm), Insights (optional), and JS linters; Sail and non-Sail pairs provided
---

# Quality Checks (Laravel)

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.12.0 guidance on PHP 8.5 locally with Composer requiring PHP `^8.4`, Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4.3, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

Run automated checks before handoff or completion. Keep output clean.

## PHP Style & Lint

```
# Check
sail pint --test    # or: vendor/bin/pint --test

# Fix
sail pint           # or: vendor/bin/pint
```

## Static Analysis (choose your tool)

```
# PHPStan example
sail vendor/bin/phpstan analyse --memory-limit=1G   # or: vendor/bin/phpstan analyse --memory-limit=1G

# Psalm example
sail vendor/bin/psalm                               # or: vendor/bin/psalm
```

## Insights (optional, if installed)

```
sail artisan insights --no-interaction --format=json --flush-cache   # or: php artisan insights --no-interaction --format=json --flush-cache
```

## Tests

```
sail artisan test --parallel   # or: php artisan test --parallel
```

## Frontend (if applicable)

```
sail pnpm run lint    # or: pnpm run lint
sail pnpm run types   # or: pnpm run types
```

## Checklist

- [ ] Pint clean (no errors)
- [ ] Static analysis passes
- [ ] Tests pass locally (parallel where possible)
- [ ] Frontend lint/types clean (if present)
- [ ] No noisy logs/errors during runs
