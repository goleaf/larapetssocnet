# Livewire Performance

Use this guide for Livewire 4 components, full-page components, nested components, islands, modals, and high-traffic widgets.

## Baseline
- Target Livewire 4.3 on Laravel 13.12.0 and the local PHP 8.5 runtime, while respecting this app's Composer `^8.4` floor and Laravel 13's PHP >= 8.3 support.
- Use Laravel Boost `application_info` and `search-docs` before changing Livewire conventions or framework-sensitive behavior.
- Prefer official Livewire 4 APIs over deprecated Volt or Livewire 3 patterns. Use `Route::livewire()` for page components.
- Prefer single-file components for focused components and multi-file components for complex components. Check existing component format and `config/livewire.php` before creating or converting components.
- Use Tailwind v4 `data-loading:*` variants on controls that trigger Livewire requests when the loading state changes the user's next action.

## Component Data
- Avoid heavy database work in `render()`. Use focused query methods, `#[Computed]`, lazy/defer loading, islands, or services so expensive data is fetched only when needed.
- Keep public properties small, serializable, and authorized: IDs, booleans, strings, scalar filters, and compact arrays. Do not store large Eloquent collections, full graph-loaded models, binary data, or broad DTO payloads in public properties.
- Use `#[Locked]` for client-immutable IDs and sensitive state, `#[Url]` for bookmarkable filters, and `#[Session]` only for small user-specific state.
- Clear stale computed values with `unset($this->property)` after writes. Use persisted or shared computed caching only when invalidation is explicit and tested.

## Rendering
- Every `@foreach` loop and nested Livewire component loop must include a stable `wire:key` or `:wire:key` based on immutable IDs.
- Prefer Blade components for static fragments. Use nested Livewire components only when the child needs independent state, requests, polling, or isolation.
- Use islands for expensive sections that should update independently without turning every repeated element into a nested component.
- Do not place `@island` inside loops or conditionals; put the loop or condition inside the island when isolation is needed.
- Use lazy loading for below-the-fold work, defer loading for above-the-fold work that should not block the first render, and bundled loading when many similar lazy/deferred components would otherwise create too many requests.
- Use `#[Renderless]`, `.renderless`, or `skipRender()` for analytics, counters, and side effects that do not change visible state.
- Use async actions only for independent work that can safely run without blocking the rest of the component.
- Use `wire:show` or Alpine for simple show/hide behavior and `wire:navigate` for internal navigation where appropriate.
- Current feed pattern: the center feed stream loads eagerly, while left/right sidebar widgets use `lazy.bundle` with skeleton placeholders so desktop sidebars share one lazy request and hidden mobile sidebars avoid eager work.
- Current post/comment side-effect pattern: draft autosaves and copy-link tracking use `#[Renderless]`; polling for new feed posts uses `#[Async]` because it is independent of the main feed interactions.
- Avoid islands when the section derives directly from parent filter, cursor, modal, or tab state and would need tight parent re-render coordination.

## Queries
- Every component list query must define selected parent/relation columns, eager loads, aggregate counts/existence flags, pagination, and deterministic sorting before rendering.
- Do not run per-row reaction, saved, follow, media, policy-adjacent, or count queries in component templates.
- For infinite scroll, store appended IDs or cursors, then rehydrate rows through the same visibility and authorization scopes.

## Tests
- Use focused Pest and Livewire tests for payload-sensitive behavior, query count boundaries, lazy/deferred placeholders, cache invalidation, locked-property tampering, and infinite-scroll pagination.
- Use `Livewire::withoutLazyLoading()` when a test must assert final lazy/deferred content instead of placeholders.
