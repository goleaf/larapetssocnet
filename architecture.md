# Architecture

PetSocial is a Laravel 13 application with feature-oriented domain folders and a shared-hosting web surface at the repository root.

## Runtime Shape

- PHP 8.4, Laravel 13, Breeze auth, Pest 4, PHPUnit 12.
- SQLite is the local/default deployment database.
- Tailwind 4 runs after Sass through PostCSS.
- Vite builds public assets into root `build/`.
- The repository root is the deployed document surface; root `.htaccess` keeps Laravel internals private.
- Shared-hosting deployment uploads one archive over FTP, then runs a token-protected server-side cleanup/extract step that preserves remote SQLite/runtime state on normal deploys.
- Production auth mail defaults to the app's `phpmail` transport so registration, verification, password reset, magic-link, and security emails can use PHP `mail()` on hosts where `proc_open()` is disabled.

## Layers

- Controllers in `app/Http/Controllers` stay thin and delegate behavior to actions, services, policies, Form Requests, and models.
- Validation and authorization belong in `app/Http/Requests` and policies unless the existing module has a narrower convention.
- Domain behavior lives in `app/Actions`, `app/Services`, `app/Models`, `app/Policies`, and `app/Support`.
- Views live in `resources/views` and should stay presentational.
- Alpine is used for small local interaction state, not for replacing server-side authorization or validation.

## Access Rules

Application pages are private by default. Keep Explore, search, profiles, posts, pets, adoption, marketplace, events, hashtags, tips, groups, feeds, messages, notifications, and settings behind authenticated application middleware unless product policy explicitly changes.

## Domain Map

- Auth and account: Breeze controllers, full-page Livewire auth/onboarding pages, one focused auth schema migration, auth audit logging, verified-email gating, onboarding completion tracking, encrypted two-factor fields, pending email changes, account status tracking, failed-login counters, `users.last_active_at` online presence, and separate OAuth social account identities.
- Auth mail delivery: `AuthMailDispatcher` centralizes queued auth mail handoff and reports transport failures without turning registration, reset, verification, or magic-link requests into user-facing server errors.
- User profiles: the `/@username` route is a full-page Livewire component with lazy child tab components and a nested edit modal; profile edit modal opening, saves, and cover repositioning authorize through owner-only `UserPolicy` abilities, while `UpdateProfileAction` owns modal validation and persistence.
- Feed and posts: full-page Livewire feed shell, eager center feed stream, lazy Livewire feed sidebars, shared Livewire post composer entry points, Livewire post-card islands, union-backed feed candidate queries, Latest/Best ranking preference, read-position memory, feed mutes, cursor pagination, post cards, reactions, comments, saves, shares, reports, and immediate soft-delete with queued post cleanup.
- Social graph: follows, pet follows, blocks, requests, counters, notifications.
- Pets and adoption: the canonical `/pets/@{pet:slug}` profile route is a full-page Livewire wrapper with reactive tab state delegated through the existing pet show controller/view; pet profiles, galleries, health logs, adoption browse/listing flows.
- Groups: membership, roles, privacy, archive/read-only lifecycle.
- Discovery: Explore, search, hashtags, trending.
- Messaging: inbox threads, conversations, notifications.
- Marketplace and activities: listings, events, contests.

## Testing Standard

Feature tests prove HTTP, middleware, database, authorization, and user-visible behavior. Unit tests prove services, architecture guards, tooling, and pure business logic. See `controller-testing.md` and `hooks.md`.
