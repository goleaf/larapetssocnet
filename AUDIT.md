# Project Audit

- Generated: 2026-05-16 21:04:32 UTC
- Project: `/Users/andrejprus/Herd/larapetssocnet`
- Scope: tracked and pending project files plus maintained repository markdown, excluding generated dependency/build/runtime directories and the external `.ai` skill cache.
- Primary quality gate: `composer quality`
- Latest verification: `composer quality` passed with 540 tests, 2216 assertions, 96.2% Pest type coverage, SCSS linting, and Vite production build.

## Toolchain

- PHP: 8.4.16
- Laravel framework: 13.9.0
- Laravel Boost: 2.4.6
- Laravel Breeze: 2.4.1
- Laravel Pint: 1.29.1
- Pest: 4.7.0
- PHPUnit: 12.5.24
- Tailwind CSS: 4.3.0
- Vite: 8.0.13
- Alpine.js: 3.15.12
- daisyUI: 5.5.19

## Current Inventory

- Project files scanned: 1,326
- Maintained markdown files scanned, including hidden agent docs: 194
- Shared-hosting web surface: root `index.php`, `.htaccess`, `build/`, `images/`, `favicon.ico`, and `robots.txt`; the former `public/` document-root directory is absent.
- Eloquent model files: 44
- HTTP controller files: 71
- Form request files: 61
- Migration files: 63
- Test files: 166
- Application routes excluding vendor routes: 209

## Automated Quality Coverage

`composer quality` is the local source of truth for broad verification. It runs:

- `composer validate --strict`
- Pint in test mode
- PHPStan/Larastan
- Rector dry run
- Pest type coverage
- Full Pest suite
- SCSS linting
- Vite production build

Additional local guards cover:

- `git diff --check`
- No unresolved merge conflict markers
- No stale Laravel/Pest/PHPUnit/Tailwind version references in maintained markdown
- No placeholder truth assertions in tests
- No starter-template comments in tests
- No focused, skipped, or todo Pest tests
- No common raw HTTP status assertions where semantic response assertions are available
- No application-level debugging helpers or direct `env()` access outside configuration

## Documentation Status

The root AI guidance files and local skill guides are aligned with the current Laravel 13, Pest 4, PHPUnit 12, Tailwind 4 stack, and shared-hosting root web surface. `tests/Unit/DocumentationVersionAlignmentTest.php` scans maintained repository markdown, including hidden agent skill files, to keep that alignment from drifting. The external `.ai` skill cache is intentionally excluded because it contains third-party reference snapshots for multiple framework generations.

## Notes

This audit is intentionally concise. Generated inventories of every model, controller, route, and schema object become stale quickly in this project; use Artisan, Laravel Boost, and the executable tests for current detail.
