# Changelog

## Unreleased

### Added
- Added `blocks` pivot table migration with composite primary key (`blocker_id`, `blocked_id`) and `created_at` timestamp.
- Added `database/seeders/BlockSeeder.php` for ORM-based block graph seeding and counter rebuild.

### Changed
- Updated block relationships in `User` model to use the `blocks` table.
- Updated `Block` pivot model to match `blocks` schema and `created_at`-only lifecycle.
- Updated `BlockService` block creation to ORM `firstOrCreate` on `Block` to preserve idempotency with the new schema.
- Updated block feature test assertions to validate records in `blocks` table.

### Tests
- Verified passing suites:
  - `tests/Feature/BlockTest.php`
  - `tests/Unit/BlockServiceTest.php`
  - `tests/Feature/OrmComplianceTest.php`
  - `tests/Feature/BlockOrmComplianceTest.php`
