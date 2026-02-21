# Repository Guidelines

## Project Structure & Module Organization
This repository is currently a clean bootstrap. As code is added, keep a standard Laravel layout:
- `app/` for domain logic (actions, services, models)
- `routes/` for HTTP entry points (`web.php`, `api.php`)
- `database/migrations` and `database/seeders` for schema and seed data
- `resources/` for Blade views and frontend assets
- `tests/Feature` for integration flows and `tests/Unit` for isolated logic
- `config/` for environment-driven settings

Use feature-based subfolders in `app/` when possible (example: `app/Actions/Pets/`).

## Build, Test, and Development Commands
Use Composer and Artisan as the primary workflow:
- `composer install` installs PHP dependencies.
- `cp .env.example .env && php artisan key:generate` initializes local environment config.
- `php artisan migrate --seed` applies schema changes and seed data.
- `php artisan serve` starts the app locally (`http://127.0.0.1:8000`).
- `npm install && npm run dev` builds frontend assets with Vite in watch mode.
- `php artisan test` runs the test suite.

If `composer.json` scripts exist (for example, `composer test`), prefer those in CI.

## Coding Style & Naming Conventions
Follow PSR-12 and Laravel conventions:
- Indentation: 4 spaces in PHP, 2 spaces in JS/CSS.
- Classes: `StudlyCase` (example: `PetProfileController`).
- Methods/variables: `camelCase`.
- Tables/columns: `snake_case`; pivot tables alphabetical (`pet_user`).
- Migrations: timestamp + intent (`2026_02_21_000000_create_pets_table.php`).

Run `./vendor/bin/pint` before opening a pull request.

## Testing Guidelines
Use Pest/PHPUnit in `tests/`:
- `tests/Feature` for HTTP, middleware, and database behavior.
- `tests/Unit` for pure business logic.
- Test names should describe behavior (example: `it_creates_a_pet_profile`).

Add tests for every bug fix and user-facing feature. Cover at least one success path and one failure path for new logic.

## Commit & Pull Request Guidelines
No established Git history is available yet, so use Conventional Commits:
- `feat: add pet profile CRUD`
- `fix: prevent duplicate adoption requests`

Each PR should include:
- clear summary of scope
- linked issue (`Closes #12`)
- migration or config notes
- screenshots for UI changes
- test evidence (summary from `php artisan test`)
