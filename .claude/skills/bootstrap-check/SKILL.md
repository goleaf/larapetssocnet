---
name: laravel:bootstrap-check
description: Detect Sail/non‑Sail and print the right command pairs for your environment; verify dependencies and key services are reachable
---

# Bootstrap Check (Laravel)

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.12.0 guidance on PHP 8.5 locally with Composer requiring PHP `^8.4`, Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4.3, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

Quickly determine if the project should run with Sail or host tools, then list the correct commands for this session.

## Detect Runner

Run this snippet in your project root:

```bash
if [ -f sail ] || [ -x vendor/bin/sail ]; then
  echo "Sail detected. Use: sail artisan|composer|pnpm ...";
else
  echo "Sail not found. Use host tools: php artisan, composer, pnpm ...";
fi
```

Optional portable alias:

```bash
alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'
```

## Command Pairs

- `sail artisan about`    | `php artisan about`
- `sail artisan test`     | `php artisan test`
- `sail artisan migrate`  | `php artisan migrate`
- `sail composer install` | `composer install`
- `sail pnpm install`     | `pnpm install`
- `sail pnpm run dev`     | `pnpm run dev`

## Service Smoke Checks

- DB: `sail mysql -e 'select 1'` or `mysql -e 'select 1'`
- Cache: `sail redis ping` or `redis-cli ping`

