---
name: laravel:e2e-playwright
description: Generic E2E patterns with Playwright—state setup, seeds, test IDs, auth, environment, and Sail integration
---

# E2E Playwright (Laravel)

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.9 guidance on PHP 8.4 with Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

Keep E2E tests reliable, fast, and maintainable.

## Environment

```
# Sail
sail pnpm playwright:test

# Non‑Sail
pnpm playwright:test
```

Use a dedicated `.env.playwright` and rebuild schema with `migrate:fresh --seed` before running.

## State & Seeds

- Provide seeders for common scenarios (users, roles, demo content)
- Use factories for per‑test setup; reset state between specs

## Test IDs & Selectors

- Prefer `data-testid` attributes over CSS paths
- Keep selectors stable through refactors

## Auth

- Reuse storage state when possible (logged‑in cookies/session)
- Otherwise create user via API/setup to avoid UI login flakiness

## Patterns

- Break large flows into steps; assert key milestones
- Record videos/screenshots only on failure to keep suites fast

