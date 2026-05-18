---
name: laravel:complexity-guardrails
description: Keep cyclomatic complexity low; flatten control flow, extract helpers, and prefer table-driven/strategy patterns over large switches
---

# Complexity Guardrails

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.9 guidance on PHP 8.4 with Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

Design to keep complexity low from day one.

## Targets

- Cyclomatic complexity per function ≤ 7 (start splitting at 5)
- Function length ≤ 80 lines (aim for ≤ 30)
- One responsibility per function; one axis of variation per module

## Tactics

- Use early returns and guard clauses; avoid deep nesting
- Extract branch bodies into named helpers
- Replace long if/else/switch with tables (maps) or strategies
- Separate phases: parse → validate → normalize → act

## Signs to refactor now

- Hard-to-test code paths
- Repeated conditionals with subtle differences
- Mixed concerns (IO, validation, transformation) in one method

