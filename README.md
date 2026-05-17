# PetSocial

PetSocial is a Laravel social network for pet profiles, feeds, groups, adoption browsing, marketplace listings, messaging, notifications, health logs, moderation, and shared media.

Application browsing pages are private by default. Guests can access authentication and system pages such as login, registration, password reset, email verification, and the banned notice; signed-in users can access Explore, search, profiles, posts, pets, adoption, marketplace, events, hashtags, tips, groups, feeds, messages, and settings.

## Stack

- PHP 8.4
- Laravel 13
- SQLite by default for local and shared-hosting deployments
- Pest 4 and PHPUnit 12
- Larastan/PHPStan, Rector, and Pint for PHP quality checks
- Tailwind CSS 4, Sass, Vite 8, Alpine 3, and daisyUI 5

## Local Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install
npm run build
```

This project is structured for shared hosting where the repository root is the web-served directory. The Laravel front controller, `.htaccess`, `build/`, `images/`, `favicon.ico`, and `robots.txt` live at the root; private Laravel folders are blocked by `.htaccess`. For active frontend work, run `npm run dev`; for production-like asset checks, run `npm run build`, which writes Vite assets to `build/`.

## Daily Commands

```bash
composer test
composer analyse
composer lint
composer rector:dry
composer test:type-coverage
npm run lint:scss
npm run build
composer quality
```

Use `composer quality` for the broad local quality gate. It validates Composer metadata, checks Pint, runs PHPStan, performs a Rector dry run, checks Pest type coverage, runs the full Pest suite, lints SCSS, and builds assets.

## Testing

Tests are written with Pest. Feature tests cover HTTP workflows, authorization, database behavior, and user-visible flows. Unit tests cover services, architecture rules, tooling configuration, and pure business logic.

Architecture quality tests guard against placeholder assertions, starter-template comments, focused or skipped tests, raw common HTTP status assertions, stale markdown version references, debugging helpers in application code, and direct `env()` access outside configuration.

Run focused tests while iterating:

```bash
php artisan test --compact tests/Feature/PetFeatureTest.php
php artisan test --compact --filter="profile"
```

Run the full suite before delivery:

```bash
php artisan test --compact
```

## Deployment

The repository includes an FTP deployment workflow for shared hosting:

- `.github/workflows/deploy-ftp.yml`
- `scripts/prepare-ftp-deploy.sh`
- `scripts/ftp-upload.php`

Required GitHub secrets are `APP_KEY`, `FTP_HOST`, `FTP_USERNAME`, `FTP_PASSWORD`, and `MAIL_PASSWORD`. Runtime deployment values such as `APP_URL`, `REMOTE_BASE`, `FTP_SERVER_DIR`, and mail settings can be configured as GitHub Actions variables.

The deployment package keeps Laravel application code under `laravel/`, publishes the shared-hosting entry point and root assets at the FTP target, and preserves remote SQLite/runtime data unless the manual `include_sqlite` workflow input is enabled for first installation.
