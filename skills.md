# Project Skills

This project exposes compact local router skills under `.agents/skills`, stores detailed project guidance under `skills/*.md`, and vendors the upstream Superpowers Laravel pack under `.claude/skills`.

## Mandatory Activation Workflow

Always activate `using-laravel-superpowers` first for Laravel implementation, review, debugging, testing, documentation, and maintenance tasks in this repository. Then activate every other matching project router or external skill for the affected domains. The Superpowers router is the baseline companion, not a substitute for domain, UI, design, security, performance, workflow, test-hook, memory, Pest, or Tailwind skills.

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
- `livewire-development`: Boost-generated Livewire 4 skill for component formats, islands, async actions, directives, and testing. Use it alongside project UI/performance routers; project rules still override generic examples.

## Detailed Guides

The detailed guides in `skills/` are intentionally not all exposed as top-level local skills. Open only the guide matching the task. Use `skills/skill-map.md` as the index when the right guide is not obvious.

## Installed Superpowers Laravel Pack

The upstream `jpcaparas/superpowers-laravel` skills are installed project-locally:

- `.claude/skills/`: 52 Laravel Superpowers skill folders plus existing project Claude skills.
- `.claude/commands/superpowers-laravel/`: 39 command wrappers for Claude Code slash-command workflows.
- `.agents/skills/using-laravel-superpowers`: the compact Codex router that points to the installed pack without exposing every upstream guide at once.

Every Laravel Superpowers skill carries this app's Laravel 13.12.0 / PHP 8.5 local / PHP `^8.4` dependency baseline. Use project rules, Laravel Boost guidance, and `skills/*.md` before applying generic upstream examples. Open the matching `.claude/skills/<skill>/SKILL.md` only when a task needs deeper Superpowers detail.

## Hermes Agent Team

Hermes-specific operating rules live in `HERMES.md` and `.hermes.md`. The global Hermes setup includes the `/laravel-team` bundle plus reusable Laravel skills under `~/.hermes/skills/laravel/` for orchestration, N+1 audits, Livewire performance, database indexes, testing, deployment performance, and security/performance review.

Use `delegate_task` for complex Hermes work. Keep subagents scoped, give them full context, require structured summaries, and let the parent agent synthesize results and run final verification.

## Performance-First Rule Updates

Rules, AI instructions, and skills must stay aligned with the installed Laravel 13.12.0, Livewire 4.3.0, current local PHP 8.5 runtime, and Composer `^8.4` dependency floor. Laravel 13 supports PHP >= 8.3, but this project should not lower its runtime requirement without a dependency audit. For Laravel or Livewire behavior, use Laravel Boost `application_info` and `search-docs` before editing.

Use `skills/boost-ai-guidelines.md`, `skills/livewire-performance.md`, `skills/query-optimization.md`, `skills/eager-loading-patterns.md`, `skills/pagination-patterns.md`, and `skills/testing.md` for performance-sensitive work. Every Eloquent list query must explicitly define eager loads, selected parent/relation columns, pagination, deterministic sorting, and aggregate counts or existence flags. Every Livewire component must avoid heavy `render()` queries, large public properties, unstable loop keys, and unnecessary re-renders. Prefer official Livewire 4 `lazy.bundle` / `defer.bundle`, `#[Async]` / `.async`, `#[Renderless]` / `.renderless`, and `data-loading` APIs when the affected component is independent enough to benefit.

Before committing performance-sensitive work, run the project-specific Performance Checklist, Livewire Component Checklist, Eloquent Query Checklist, and Deployment Checklist in `skills/boost-ai-guidelines.md`.

## Adding A New Skill

Add a new `.agents/skills/<name>/SKILL.md` only when a recurring workflow needs a compact router. Put detailed instructions in `skills/<topic>.md`, then update `AGENTS.md`, `skills.md`, `skills/skill-map.md`, `FEATURES.md`, and `CHANGELOG.md`.
