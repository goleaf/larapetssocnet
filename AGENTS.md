# Repository Guidelines

## Project Structure & Module Organization
This is an active Laravel 13 PetSocial application. Keep changes aligned with the current feature-based layout:
- `app/Actions/`, `app/Services/`, `app/Models/`, `app/Policies/`, and `app/Support/` for domain logic
- `app/Http/Controllers/` and `app/Http/Requests/` for HTTP workflows and validation
- `routes/` for web and included route files
- `database/migrations`, `database/factories`, and `database/seeders` for schema and data setup
- `resources/views`, `resources/js`, and `resources/scss` for Blade, Alpine, and Tailwind/Sass assets
- root `index.php`, `.htaccess`, `build/`, `images/`, `favicon.ico`, and `robots.txt` for the shared-hosting web surface
- `tests/Feature` for HTTP/database/user flows and `tests/Unit` for services, support classes, and architecture guards
- `skills/` for detailed project-specific AI workflow guides, with only compact router skills exposed under `.agents/skills/`
- `.claude/skills/` for the project-installed Superpowers Laravel skill pack and `.claude/commands/superpowers-laravel/` for its command wrappers
- root `design.md`, `architecture.md`, `skills.md`, `hooks.md`, `controller-testing.md`, `HERMES.md`, and `.hermes.md` for project-wide AI guidance

Use existing domain subfolders before creating new base folders.
Do not create or use `app/Jobs`; keep background-style application side effects in services, actions, commands, observers, notifications, or other existing domain folders instead.

## Build, Test, and Development Commands
Use Composer and Artisan as the primary workflow:
- `composer install` installs PHP dependencies.
- `cp .env.example .env && php artisan key:generate` initializes local environment config.
- `php artisan migrate --seed` applies schema changes and seed data.
- `npm install && npm run build` installs and builds frontend assets.
- `npm run dev` runs Vite for active frontend work.
- `composer test` clears config and runs the Pest suite.
- `composer quality` runs the full local gate: Composer validation, Pint, PHPStan, Rector dry-run, Pest type coverage, tests, SCSS lint, and Vite build.

The project root is the shared-hosting document surface. Root `.htaccess` must keep Laravel internals private when Apache points at the repository root; do not reintroduce a `public/` web root unless the deployment strategy changes.

Application pages are closed to guests by default. Keep Explore, search, profiles, posts, pets, adoption, marketplace, events, hashtags, tips, groups, feeds, messages, notifications, and settings behind the authenticated application route group unless a new product-policy change explicitly opens them.

## Laravel, Livewire & Performance Rules
Use the installed Laravel 13.12.0, Livewire 4.3.0, and PHP 8.4+ runtime. Laravel 13 supports PHP >= 8.3, but this application's current lock file does not install on PHP 8.3 because locked Symfony 8.1 components and `spatie/laravel-activitylog` 5 require PHP 8.4+. Before changing Laravel or Livewire behavior, verify current official guidance through Laravel Boost `application_info` and `search-docs`, then apply the project skills that match the touched domain.

Every Eloquent list query must explicitly define its relation graph, selected columns, pagination strategy, deterministic sorting, and aggregate counts or existence flags where needed. Start from `Model::query()`, prefer relationships and scopes, use `with()`, constrained eager loads, `morphWith()`, `withCount()`, `withExists()`, and cursor pagination for feeds/infinite scroll. Never access relationship properties, count relations, check saved/reaction/follow state, or run policy-adjacent lookup queries inside Blade or Livewire loops unless that data was eagerly loaded or batched first.

`Model::preventLazyLoading(! app()->isProduction())` must stay enabled unless an exception is documented in the affected guide and covered by tests. Laravel automatic eager loading is beta; do not use it as the primary N+1 strategy for critical surfaces.

Livewire 4 components must keep public state small and serializable. Avoid heavy queries in `render()`; move expensive data to focused query methods, `#[Computed]` properties, cached services, lazy/defer-loaded components, islands, async actions, or renderless actions when appropriate. Store model IDs or compact arrays instead of large Eloquent models/collections in public properties, use `#[Locked]` for client-immutable identifiers, use `#[Url]` only for bookmarkable filters, use `#[Session]` only for small user-specific state, and include stable `wire:key` / `:wire:key` values in every rendered loop or nested component loop.

Query and cache performance changes must be measured. Add or preserve indexes for new `where`, `join`, `exists`, and `order by` paths; cache expensive read models with explicit keys and invalidation; use tag-aware cache only with a fallback for cache stores that do not support tags; keep production deploys on `php artisan optimize`, built assets, OPcache reset, queue worker restart, and `APP_DEBUG=false`.

Performance-sensitive code must be tested with focused Pest coverage: assert query counts or bounded queries for critical list pages, assert lazy-loading prevention remains enabled, test cache invalidation, and cover success plus denied/empty states for visibility-aware queries.

## AI Skill Activation
This project has domain-specific skills available. You MUST activate `using-laravel-superpowers` at the start of every Laravel task in this repository, including implementation, review, debugging, testing, documentation, and maintenance work. You MUST also activate every other matching project/router skill for the domains touched by the prompt; do not treat Superpowers as a replacement for project-specific, Pest, Tailwind, security, performance, workflow, or memory skills.

- `using-laravel-superpowers` — Broad Laravel workflow router installed into this project from `jpcaparas/superpowers-laravel`; use it as the default companion skill for most Laravel changes here. The full upstream Superpowers Laravel skill pack is vendored under `.claude/skills/`, but project rules, Laravel Boost, and compact local routers take precedence.
- `larapetssocnet-domain-guides` — Project-specific router for feeds, posts, reactions, follows, pets, adoption, groups, privacy, moderation, hashtags, search, listings, media, and service/query patterns documented under `skills/`.
- `larapetssocnet-ui-guides` — Project-specific router for Blade, Alpine, Tailwind, forms, accessibility, localization, charts, and layout conventions documented under `skills/`.
- `larapetssocnet-design-guides` — Project-specific router for the Warm Editorial visual system, design tokens, shared UI primitives, responsive shell rules, and Playwright visual review expectations documented in `design.md` and `skills/design.md`.
- `larapetssocnet-security-guides` — Project-specific router for authorization, visibility, moderation, guest access, and sanitization rules documented under `skills/`.
- `larapetssocnet-performance-guides` — Project-specific router for feed/query performance, eager loading, counters, pagination, and SQLite-aware data patterns documented under `skills/`.
- `larapetssocnet-workflow-guides` — Project-specific router for testing, localization/light-UI workflow, changelog/commit flow, and common service/request patterns documented under `skills/`.
- `larapetssocnet-test-hooks-guides` — Project-specific router for local git hooks, changed-controller test coverage, controller-test mapping, and quality-gate expectations documented in `hooks.md`, `controller-testing.md`, `skills/hooks.md`, and `skills/controller-testing.md`.
- `larapetssocnet-memory-guides` — Project-specific router for memory lookup/update rules, durable preferences, and prior rollout context documented in `skills/memory.md`.
- Detailed markdown guides in `skills/*.md` and upstream Superpowers guides in `.claude/skills/` are intentionally not all exposed as Codex local skills, to avoid overflowing the skills context budget. Use the compact router skills above to find and read only the matching guide files.
- `pest-testing` — Tests applications using the Pest 4 PHP framework. Activates when writing tests, creating unit or feature tests, adding assertions, testing Livewire components, architecture testing, debugging test failures, working with datasets or mocking; or when the user mentions test, spec, TDD, expects, assertion, coverage, or needs to verify functionality works.
- `tailwindcss-development` — Styles applications using Tailwind CSS v4 utilities. Activates when adding styles, restyling components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode, colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind, restyle, hero section, cards, buttons, or any visual/UI changes.

## Coding Style & Naming Conventions
Follow PSR-12 and Laravel conventions:
- Indentation: 4 spaces in PHP, 2 spaces in JS/CSS.
- Classes: `StudlyCase` (example: `PetProfileController`).
- Methods/variables: `camelCase`.
- Tables/columns: `snake_case`; pivot tables alphabetical (`pet_user`).
- Migrations: timestamp + intent (`2026_02_21_000000_create_pets_table.php`).

Run `vendor/bin/pint --dirty --format=agent` after PHP edits and before opening a pull request.

## Testing Guidelines
Use Pest in `tests/`:
- `tests/Feature` for HTTP, middleware, and database behavior.
- `tests/Unit` for pure business logic.
- Test names should describe behavior (example: `it_creates_a_pet_profile`).

Add tests for every bug fix and user-facing feature. Cover at least one success path and one failure path for new logic. Avoid placeholder truth assertions, focused/skipped/todo tests, and raw common HTTP status assertions when semantic assertions are available.

## Commit & Pull Request Guidelines
Use Conventional Commits:
- `feat: add pet profile CRUD`
- `fix: prevent duplicate adoption requests`

## End-of-Prompt Maintenance Rule
After every implementation prompt, treat documentation and git hygiene as part of the work before the final response:
- Update all affected Markdown files, including root guidance files, `README.md`, workflow docs, and relevant `skills/*.md` guides when behavior, commands, architecture, or feature scope changes.
- Update `FEATURES.md` whenever feature status, feature scope, or feature implementation notes change.
- Update `CHANGELOG.md` under `Unreleased` with user-facing `Added`, `Changed`, `Fixed`, `Removed`, or `Tests` entries for completed work.
- Check `git status --short` and summarize the intended changes. Stage only files that belong to the completed task, preserving unrelated dirty-tree changes.
- Commit with a Conventional Commits message when the prompt asks for git delivery or the completed task is ready for the repository history. Push only when explicitly requested.

Each PR should include:
- clear summary of scope
- linked issue (`Closes #12`)
- migration or config notes
- screenshots for UI changes
- test evidence, preferably from `composer quality` or the most focused passing command

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- livewire/livewire (LIVEWIRE) - v4
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/breeze (BREEZE) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- rector/rector (RECTOR) - v2
- alpinejs (ALPINEJS) - v3
- tailwindcss (TAILWINDCSS) - v4

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
