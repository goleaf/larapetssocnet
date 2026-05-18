# Project Skills

This project exposes compact local router skills under `.agents/skills` and stores detailed project guidance under `skills/*.md`.

## Exposed Router Skills

- `laravel:using-laravel-superpowers`: default Laravel workflow guidance.
- `larapetssocnet-domain-guides`: feature/domain routing for feeds, posts, pets, groups, messaging, search, privacy, and services.
- `larapetssocnet-ui-guides`: Blade, Alpine, Tailwind, forms, accessibility, layout, and widget routing.
- `larapetssocnet-design-guides`: Warm Editorial design system, tokens, layout, and visual documentation.
- `larapetssocnet-security-guides`: auth, authorization, visibility, moderation, guest access, and sanitization routing.
- `larapetssocnet-performance-guides`: query, eager-loading, pagination, counters, and performance checks.
- `larapetssocnet-workflow-guides`: testing, localization, changelog, and implementation close-out routing.
- `larapetssocnet-test-hooks-guides`: local git hooks, controller coverage, and quality gates.
- `larapetssocnet-memory-guides`: memory lookup/update rules and durable user preferences.
- `pest-testing`: Pest 4 test authoring and verification.
- `tailwindcss-development`: Tailwind 4/Sass styling work.

## Detailed Guides

The detailed guides in `skills/` are intentionally not all exposed as top-level local skills. Open only the guide matching the task. Use `skills/skill-map.md` as the index when the right guide is not obvious.

## Adding A New Skill

Add a new `.agents/skills/<name>/SKILL.md` only when a recurring workflow needs a compact router. Put detailed instructions in `skills/<topic>.md`, then update `AGENTS.md`, `skills.md`, `skills/skill-map.md`, `FEATURES.md`, and `CHANGELOG.md`.
