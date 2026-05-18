# Project Skills

This project exposes compact local router skills under `.agents/skills`, stores detailed project guidance under `skills/*.md`, and vendors the upstream Superpowers Laravel pack under `.claude/skills`.

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

## Installed Superpowers Laravel Pack

The upstream `jpcaparas/superpowers-laravel` skills are installed project-locally:

- `.claude/skills/`: 52 Laravel Superpowers skill folders plus existing project Claude skills.
- `.claude/commands/superpowers-laravel/`: 39 command wrappers for Claude Code slash-command workflows.
- `.agents/skills/using-laravel-superpowers`: the compact Codex router that points to the installed pack without exposing every upstream guide at once.

Use project rules, Laravel Boost guidance, and `skills/*.md` before applying generic upstream examples. Open the matching `.claude/skills/<skill>/SKILL.md` only when a task needs deeper Superpowers detail.

## Adding A New Skill

Add a new `.agents/skills/<name>/SKILL.md` only when a recurring workflow needs a compact router. Put detailed instructions in `skills/<topic>.md`, then update `AGENTS.md`, `skills.md`, `skills/skill-map.md`, `FEATURES.md`, and `CHANGELOG.md`.
