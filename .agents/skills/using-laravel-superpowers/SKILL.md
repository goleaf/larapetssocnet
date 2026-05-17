---
name: laravel:using-laravel-superpowers
description: Read first in Laravel repos; explains runner selection (Sail vs non-Sail), core workflows, and how to apply superpowers skills in Laravel projects without platform lock-in
---

# Using Superpowers in Laravel Projects

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

Use the project guide files under `skills/` for deeper workflow notes when a task needs them.

## Core Workflows

- Test-Driven Development first: use `pest-testing`, then read `skills/testing.md` when project-specific coverage rules matter.
- Database changes: read `skills/sqlite.md`, `skills/eloquent-patterns.md`, and related model guides before changing schema or queries.
- Quality gates: run the smallest relevant tests first, then Pint/build checks when touched files require them.
- Queues and architecture patterns: prefer existing services, actions, and project guide files over adding new public skill entrypoints.
- Keep complexity low: choose the narrowest implementation that fits existing Laravel conventions.
- End implementation tasks with the repo close-out rule: update affected Markdown, `FEATURES.md`, `CHANGELOG.md`, and git status before the final response.

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

When a compact local router skill matches the task, use it first and load detailed guide files from `skills/` only as needed.
