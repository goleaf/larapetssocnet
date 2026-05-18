---
name: laravel:using-laravel-superpowers
description: Read first in Laravel repos; explains runner selection (Sail vs non-Sail), core workflows, and how to apply superpowers skills in Laravel projects without platform lock-in
---

# Using Superpowers in Laravel Projects

This plugin adds Laravel-aware guidance while staying platform-agnostic. It works in any Laravel app with or without Sail.

## Installed Project Pack

The upstream Superpowers Laravel skills from `jpcaparas/superpowers-laravel` are installed for this checkout under `.claude/skills`, with command wrappers under `.claude/commands/superpowers-laravel`.

In Codex, keep this compact router active first, then open only the matching installed skill file when deeper Superpowers guidance is needed. Project rules in `AGENTS.md`, Laravel Boost guidance, and the local `skills/*.md` guides take precedence over generic upstream examples.

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

- Test-Driven Development first: use `pest-testing`, read `skills/testing.md`, and use `.claude/skills/tdd-with-pest/SKILL.md` for upstream workflow details when useful.
- Database changes: read `skills/sqlite.md`, `skills/eloquent-patterns.md`, and `.claude/skills/migrations-and-factories/SKILL.md` before changing schema or queries.
- Quality gates: run the smallest relevant tests first, then project Pint/build checks; use `.claude/skills/quality-checks/SKILL.md` only after reconciling it with `composer quality`.
- Queues and architecture patterns: prefer existing services, actions, and project guide files, then consult matching `.claude/skills/*/SKILL.md` files for broader Laravel patterns.
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

When a compact local router skill matches the task, use it first and load detailed guide files from `skills/` or `.claude/skills/` only as needed.
