---
name: laravel:using-laravel-superpowers
description: Read first in Laravel repos; explains runner selection (Sail vs non-Sail), core workflows, and how to apply superpowers skills in Laravel projects without platform lock-in
---

# Using Superpowers in Laravel Projects

This plugin adds Laravel-aware guidance while staying platform-agnostic. It works in any Laravel app with or without Sail.

## Laravel 13 Baseline

Use these skills for this app as Laravel 13.12.0 guidance on PHP 8.5 locally with Composer requiring PHP `^8.4`, Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4.3, SQLite, and the repository-root shared-hosting web surface. Laravel Boost, `AGENTS.md`, and local `skills/*.md` guides override generic upstream examples.

## Installed Project Pack

The upstream Superpowers Laravel skills from `jpcaparas/superpowers-laravel` are installed for this checkout under `.claude/skills`, with command wrappers under `.claude/commands/superpowers-laravel`.

In Codex, keep this compact router active first, then open only the matching installed skill file when deeper Superpowers guidance is needed. Project rules in `AGENTS.md`, Laravel Boost guidance, and the local `skills/*.md` guides take precedence over generic upstream examples.

## Mandatory Activation Rule

Activate this router first for every Laravel implementation, review, debugging, testing, documentation, and maintenance task in this repository. Then activate all other matching local router skills and external skills for the domains touched by the prompt; Superpowers guidance is a baseline companion, not a replacement for project-specific, Pest, Tailwind, security, performance, workflow, or memory skills.

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
- Performance-sensitive Laravel and Livewire work: read `skills/livewire-performance.md`, `skills/query-optimization.md`, `skills/eager-loading-patterns.md`, `skills/pagination-patterns.md`, and `skills/testing.md`.
- AI/Boost guidance updates and broad performance-sensitive Laravel or Livewire work: read `skills/boost-ai-guidelines.md`, use Laravel Boost `application_info`, and search version-specific docs with `search-docs` before changing framework-sensitive behavior.
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
