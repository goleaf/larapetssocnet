---
name: laravel:using-laravel-superpowers
description: Read first in Laravel repos; explains runner selection (Sail vs non-Sail), core workflows, and how to apply superpowers skills in Laravel projects without platform lock-in
---

# Using Superpowers in Laravel Projects

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.9 guidance on PHP 8.4 with Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

This plugin adds Laravel-aware guidance while staying platform-agnostic. It works in any Laravel app with or without Sail.

## Runner Selection (Sail or non-Sail)

Use the minimal wrapper below when running commands:

```
# Prefer Sail if available, else fall back to host
alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'

# Example (both work depending on environment)
sail artisan test           # with Sail
php artisan test            # without Sail
sail composer require x/y   # with Sail
composer require x/y        # without Sail
```

See the `laravel:runner-selection` skill for detection tips, command pairs, and safety notes.

## Core Workflows

- Test-Driven Development first: use `laravel:tdd-with-pest`
- Database changes: use `laravel:migrations-and-factories`
- Quality gates: use `laravel:quality-checks` (Pint, Insights/PHPStan)
- Queues and Horizon: use `laravel:queues-and-horizon`
- Architecture patterns: `laravel:ports-and-adapters`, `laravel:template-method-and-plugins`
- Keep complexity low: `laravel:complexity-guardrails`

## Philosophy

- Favor small, testable services; avoid fat controllers/commands/jobs
- DTOs, typed Collections, and Enums when they clarify intent
- Prefer model factories in tests and model scopes for complex queries
- Verify before completion—run tests and linters clean

Use slash commands as needed:

```
/superpowers-laravel:brainstorm
/superpowers-laravel:write-plan
/superpowers-laravel:execute-plan
```

When a Laravel skill exists for your task, use it exactly as written.
