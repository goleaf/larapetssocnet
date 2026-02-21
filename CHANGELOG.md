# Changelog

## Unreleased

### Added
- Added `blocks` pivot table migration with composite primary key (`blocker_id`, `blocked_id`) and `created_at` timestamp.
- Added `database/seeders/BlockSeeder.php` for ORM-based block graph seeding and counter rebuild.
- Added follow feature architecture: `FollowService`, `FollowController`, `FollowRequestController`, `FollowPolicy`, and `CanFollow` rule.
- Added follow exceptions and JSON exception mapping: `CannotFollowSelfException`, `UserBlockedException`, `UserBannedException`.
- Added new follow notifications: `NewFollowRequest`, `FollowRequestApproved`.
- Added follow schema migrations: `create_follows_table` and `add_follow_fields_to_users_table`.
- Added follow UI pages/components: updated `follow-button`, followers/following pages, follow-requests page, and who-to-follow widget.
- Added follow test suites: `tests/Feature/FollowTest.php`, `tests/Feature/FollowRequestTest.php`, `tests/Unit/FollowServiceTest.php`.
- Added baseline skill docs required for this feature (`skills/laravel.md`, `skills/sqlite.md`, `skills/blade.md`, `skills/alpine.md`, `skills/tailwind.md`, `skills/security.md`, `skills/forms.md`, `skills/policies.md`).
- Added follow-specific skills: `skills/relations.md`, `skills/counters.md`, `skills/notifications.md`.

### Changed
- Updated block relationships in `User` model to use the `blocks` table.
- Updated `Block` pivot model to match `blocks` schema and `created_at`-only lifecycle.
- Updated `BlockService` block creation to ORM `firstOrCreate` on `Block` to preserve idempotency with the new schema.
- Updated block feature test assertions to validate records in `blocks` table.
- Migrated `User` follow relationships/methods to use `follows` with statuses (`accepted`/`pending`) and service-driven actions.
- Updated counter cache rebuild logic to include `follow_requests_count`.
- Updated social seeding follow graph to use `FollowService` and `CounterCacheService` rebuild.
- Updated `skills/testing.md` with social-action testing guidance.
- Updated routing to add dedicated follow/follow-request endpoints while keeping compatibility for existing follow/unfollow/profile flows.

### Tests
- Verified passing suites:
  - `tests/Feature/BlockTest.php`
  - `tests/Unit/BlockServiceTest.php`
  - `tests/Feature/OrmComplianceTest.php`
  - `tests/Feature/BlockOrmComplianceTest.php`
  - `tests/Feature/FollowTest.php`
  - `tests/Feature/FollowRequestTest.php`
  - `tests/Unit/FollowServiceTest.php`
  - `tests/Feature/FollowFeatureTest.php`
  - `tests/Feature/ProfileTest.php --filter=\"followers can view private profile pets tab|blocking removes follows and prevents future follows until unblocked\"`
