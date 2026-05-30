# Boost AI Guidelines

Use this guide when updating AI rules, adding Laravel or Livewire behavior, or checking agent instructions after dependency updates.

## Boost Search Docs Before Coding

- Start Laravel tasks with Laravel Boost `application_info` so the agent knows the installed PHP, Laravel, Livewire, Pest, Tailwind, and tooling versions.
- Use Boost `search-docs` before changing uncertain Laravel, Livewire, Eloquent, Blade, cache, queue, API resource, deployment, or testing behavior.
- Use broad topic queries and package filters when relevant. Prefer official Laravel and Livewire results over blog posts or older examples.
- Do not use deprecated Laravel, Livewire, Volt, Eloquent, Blade, queue, cache, or testing patterns.

## Laravel And PHP Rules

- Use Laravel 13.x patterns and PHP 8.3+ syntax.
- This app runs on PHP 8.5 locally and requires Composer PHP `^8.4`; do not lower the project runtime floor without a dependency audit.
- Prefer constructor property promotion, typed properties, readonly values, explicit return types, enums, immutable value objects, and Laravel-supported PHP attributes where they improve clarity.
- Prefer Laravel-native features before third-party packages.

## Livewire Component Checklist

- Use Livewire 4.x patterns.
- Prefer single-file components for focused components and multi-file components for complex components.
- Keep public properties small, safe, authorized, and serializable.
- Never store large Eloquent collections, full graph-loaded models, binary data, or sensitive data in public properties.
- Use `#[Computed]` for derived data and clear stale computed values with `unset($this->property)` after writes.
- Use `#[Locked]` for IDs and immutable public state.
- Use `#[Url]` only for bookmarkable filters and `#[Session]` only for small user-specific state.
- Use `#[Lazy]`, `#[Defer]`, `#[Isolate]`, `#[Async]`, `#[Renderless]`, and `@island` only when they improve performance or clarity.
- Use `@island` for expensive independent regions, but do not put islands inside loops or conditionals.
- Use `wire:show` or Alpine for simple show/hide behavior.
- Use `wire:navigate` for internal navigation where it fits the route flow.
- Use stable `wire:key` and `:wire:key` values in every loop and nested component loop.
- Add Livewire tests for important components, including locked-property tampering, lazy/deferred rendering, validation, authorization, and query bounds where relevant.

## Eloquent Query Checklist

- Start from `Model::query()` and prefer relationships and scopes before raw SQL.
- Every list query must define selected parent columns, selected relation columns, eager loads, filters, deterministic sorting, aggregate counts or existence flags, and pagination before rendering.
- When using `select()`, include primary keys and foreign keys required by eager-loaded relationships.
- Never access unloaded relationships inside loops.
- Never run relationship count, sum, existence, reaction, save, follow, block, or policy-adjacent queries inside loops.
- Use `with()`, constrained eager loads, `load()`, `loadMissing()`, `withCount()`, `withSum()`, `withExists()`, `withAvg()`, `withMin()`, and `withMax()` deliberately.
- Use `withWhereHas()` when filtering and eager loading the same relationship.
- Use `morphWith()`, `loadMorph()`, or `loadMorphCount()` for `morphTo` relation graphs.
- Keep `Model::preventLazyLoading(! app()->isProduction())` enabled unless an exception is documented and tested.
- Treat Laravel automatic eager loading as beta; do not rely on it for critical list surfaces.

## Database Performance Rules

- Never use `Model::all()` for pages, tables, exports, queued work, or APIs unless the table is proven tiny and documented.
- Use `paginate()`, `simplePaginate()`, `cursorPaginate()`, `chunkById()`, `lazyById()`, `lazy()`, or `cursor()` depending on the use case.
- Add or preserve indexes for foreign keys, filters, sorting columns, search columns, and common composite query patterns.
- Do not let user input directly control `orderBy` column names.
- Avoid raw SQL unless necessary; bind parameters safely when raw SQL is used.
- Use `DB::listen()` and `DB::whenQueryingForLongerThan()` only in local, testing, or explicit performance monitoring contexts.

## Cache Rules

- Use `Cache::remember()` for stable expensive data.
- Use `Cache::memo()` to avoid repeated cache hits inside one request or job.
- Use `Cache::touch()` when only TTL extension is needed.
- Use cache tags only when the configured driver supports them, and provide a fallback for unsupported stores.
- Do not use `Cache::flush()` on shared cache stores.
- Do not cache user-specific data with global keys.
- Avoid caching full Eloquent models unless serialization risk and invalidation are handled.
- Define clear TTL, key naming, user scoping, and invalidation rules.

## Queue Rules

- Move slow work out of HTTP and Livewire actions into queued, idempotent background work.
- This repository must not create `app/Jobs`; use existing services, actions, commands, observers, notifications, or framework/package queued classes unless the project guardrail is explicitly changed.
- Every `ShouldQueue` class must define queue name, retries, backoff, timeout, fail-on-timeout, and failed-job behavior.
- Use Laravel queue attributes where they improve clarity.
- Avoid duplicate bursty work with uniqueness, overlap protection, debouncing, idempotency keys, or durable duplicate checks.
- Queued work must avoid N+1 queries and large serialized models.

## API Rules

- API resources must not lazy-load relationships.
- Use `whenLoaded()`, `whenCounted()`, and conditional resource fields.
- Always paginate large API responses.
- Use JSON:API resources only when the API needs JSON:API compliance.
- Do not expose sensitive columns.
- Add query-count tests for important API endpoints.

## Testing Rules

- Add feature tests for critical routes.
- Add Livewire component tests for important components.
- Add query-count tests for performance-critical pages.
- Test that lazy loading is prevented in local and testing.
- Test cache invalidation.
- Test queue dispatch instead of synchronous slow work.
- Run Pint, focused Pest/PHPUnit, static analysis when installed, and relevant browser tests after changes.

## Performance Checklist Before Commit

- Confirm all changed list/read queries define selected columns, eager loads, filters, sorting, aggregates, and pagination.
- Confirm selected columns include primary keys and foreign keys needed by relationships.
- Confirm no Blade or Livewire loop performs per-row relationship, policy-adjacent, saved/reaction/follow, count, sum, or exists queries.
- Confirm cache keys, TTLs, user scoping, tag fallback, and invalidation are explicit and tested.
- Confirm slow side effects are queued or moved into project-approved background layers, not run inside the request.
- Confirm API resources use conditional fields and do not trigger lazy loading.
- Run focused tests for query counts, lazy-loading prevention, cache invalidation, queue dispatch, and denied/empty states when relevant.

## Deployment Checklist

- Production must use `APP_ENV=production` and `APP_DEBUG=false`.
- Deploy with `composer install --no-dev --optimize-autoloader`.
- Build frontend assets with the production Vite build.
- Run `php artisan optimize` during deploy.
- Reload queue workers and any active long-running services such as Reverb, Octane, or Horizon.
- Reset OPcache on shared hosting.
- Do not expose Telescope, Debugbar, verbose query logging, or other debug tools in production.
- Preserve the repository-root shared-hosting web surface and root `.htaccess` protections.
