# Hermes Laravel Operating Rules

Hermes must use Laravel 13.x, PHP 8.3+, and Livewire 4.x best practices. This checkout currently runs Laravel 13.12.0, Livewire 4.3.0, and PHP 8.5 locally through Herd while Composer requires PHP `^8.4`.

Before changing Laravel or Livewire behavior, inspect the app with Laravel Boost `application_info` and search version-specific official docs with `search-docs`. Keep project rules in `AGENTS.md`, `CLAUDE.md`, `skills.md`, and the matching `skills/*.md` guides above generic framework examples.

## Hermes Orchestration

For complex tasks, Hermes should act as orchestrator and delegate to subagents:

- Laravel backend / Eloquent
- Livewire performance
- Database/indexes
- Testing/QA
- Security/performance
- Deployment/performance
- Documentation research

Use `delegate_task` for subagents. Use `/agents` or `/tasks` to inspect running subagents when the current Hermes shell exposes those commands. Use `skills_list` and `skill_view` when available to confirm and inspect skills.

## Subagent Rules

1. Every subagent receives full context.
2. No vague goals.
3. No subagent may ask the user questions directly.
4. No subagent writes shared memory.
5. No subagent sends external messages.
6. No destructive command without parent approval.
7. No migration, delete, reset, deploy, composer major update, or npm major update without explicit risk explanation.
8. Every subagent returns a structured summary with files inspected, files changed, risks, commands run, tests run, and remaining work.
9. Parent agent verifies and synthesizes all outputs.
10. Parent agent runs final tests where possible.

## Laravel Rules

1. Never allow N+1 queries.
2. Keep `Model::preventLazyLoading(! app()->isProduction())` enabled unless a documented and tested exception exists.
3. Every Eloquent list query must define selected parent/relation columns, eager loads, pagination, deterministic sorting, and aggregate counts/existence flags before rendering.
4. Use eager loading and aggregate preload helpers such as `with()`, constrained eager loads, `withWhereHas()`, `withCount()`, `withExists()`, `withSum()`, `loadMissing()`, `morphWith()`, `loadMorph()`, and `loadMorphCount()`.
5. Avoid heavy Livewire render methods.
6. Keep Livewire public state small, serializable, and authorized.
7. Use `#[Computed]`, `#[Locked]`, `#[Lazy]`, `#[Defer]`, `@island`, `#[Async]`, `#[Renderless]`, and `#[Isolate]` appropriately, and clear stale computed values after writes.
8. Always test performance-sensitive changes.
9. Keep application browsing pages behind authenticated routes unless product policy explicitly changes.
10. Do not create `app/Jobs`; use existing services, actions, commands, observers, notifications, or other project-approved layers.
11. Use `Cache::remember()`, `Cache::memo()`, and `Cache::touch()` with explicit keys, TTLs, user scoping, and invalidation; never use `Cache::flush()` on shared stores.
12. API resources must use `whenLoaded()`, `whenCounted()`, conditional fields, pagination, sensitive-column filtering, and query-count tests.
13. Production deploy checks must include `APP_ENV=production`, `APP_DEBUG=false`, optimized Composer autoloads, production Vite assets, `php artisan optimize`, worker/service reloads when active, OPcache reset on shared hosting, and no exposed debug tooling.

## MCP Safety

- Do not expose secrets.
- Do not expose the whole home directory or root filesystem.
- Keep filesystem MCP access scoped to this project root.
- Do not give write tools to research-only subagents.
- Do not allow destructive terminal commands without parent approval.
- Disable unused MCP servers.
- Prefer project-scoped tools and Laravel Boost read-only tools for schema, logs, docs, and URLs.
