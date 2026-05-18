# Architecture

PetSocial is a Laravel 13 application with feature-oriented domain folders and a shared-hosting web surface at the repository root.

## Runtime Shape

- PHP 8.4, Laravel 13, Breeze auth, Pest 4, PHPUnit 12.
- SQLite is the local/default deployment database.
- Tailwind 4 runs after Sass through PostCSS.
- Vite builds public assets into root `build/`.
- The repository root is the deployed document surface; root `.htaccess` keeps Laravel internals private.

## Layers

- Controllers in `app/Http/Controllers` stay thin and delegate behavior to actions, services, policies, Form Requests, and models.
- Validation and authorization belong in `app/Http/Requests` and policies unless the existing module has a narrower convention.
- Domain behavior lives in `app/Actions`, `app/Services`, `app/Models`, `app/Policies`, and `app/Support`.
- Views live in `resources/views` and should stay presentational.
- Alpine is used for small local interaction state, not for replacing server-side authorization or validation.

## Access Rules

Application pages are private by default. Keep Explore, search, profiles, posts, pets, adoption, marketplace, events, hashtags, tips, groups, feeds, messages, notifications, and settings behind authenticated application middleware unless product policy explicitly changes.

## Domain Map

- Auth and account: Breeze controllers, auth audit logging, verified-email gating.
- Feed and posts: feed controller, cursor pagination, post cards, reactions, comments, saves, shares, reports.
- Social graph: follows, pet follows, blocks, requests, counters, notifications.
- Pets and adoption: pet profiles, galleries, health logs, adoption browse/listing flows.
- Groups: membership, roles, privacy, archive/read-only lifecycle.
- Discovery: Explore, search, hashtags, trending.
- Messaging: inbox threads, conversations, notifications.
- Marketplace and activities: listings, events, contests.

## Testing Standard

Feature tests prove HTTP, middleware, database, authorization, and user-visible behavior. Unit tests prove services, architecture guards, tooling, and pure business logic. See `controller-testing.md` and `hooks.md`.
