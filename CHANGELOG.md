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
- Added profile privacy toggle backend with `PrivacyService` and `PrivacyController` (`POST /settings/privacy/toggle`).
- Added private-profile locked-state view: `resources/views/profile/private.blade.php` with `noindex,nofollow` meta.
- Added privacy feature tests in `tests/Feature/PrivacyToggleTest.php`.
- Added username domain models and service: `UsernameRedirect`, `ReservedUsername`, and `UsernameService`.
- Added username support migrations: `username_changed_at`, `username_redirects`, and `reserved_usernames` tables.
- Added reserved username config/seeder: `config/reserved_usernames.php` and `ReservedUsernameSeeder`.
- Added reusable username helpers in `app/helpers.php`: `username_url()` and `at_username()`.
- Added `MentionService` and integrated mention link parsing with `@username` profile URLs.
- Added `tests/Feature/UsernameTest.php` for username URL, redirect, availability, cooldown, and helper coverage.

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
- Updated `User` privacy/follow access logic with `makePrivate()`, `makePublic()`, `canViewProfile()`, `canViewPosts()`, and discoverability scopes.
- Updated profile rendering flow to show locked private state for non-authorized viewers.
- Updated follow requests controller/view flow (`requests` dataset + pending validation on approve).
- Updated account settings privacy UI to Alpine-powered async toggle with pending-request confirmation.
- Updated navigation to show pending follow-requests badge for private accounts.
- Updated explore/search discoverability filters to exclude private and banned users.
- Updated `PostPolicy` and `FollowPolicy` checks for private-profile visibility semantics.
- Updated username validation and normalization to underscore-only format (`[a-zA-Z0-9_]`) across registration and profile updates.
- Updated profile route handling to support case-normalized canonical redirects and old-username 301 redirects.
- Updated profile settings and registration forms with live username availability checks.
- Updated `TrackLastSeen` middleware to throttle writes to every 5 minutes.
- Updated saved posts page to use saved-entry pagination, show saved timestamps, and provide inline unsave actions.
- Updated profile posts tab to render real posts with pinned-first ordering and owner pin/unpin controls.
- Updated post pinning flow to support both web form redirects and JSON responses, and added explicit unpin route.
- Updated post cards with a Share action that copies the post URL to clipboard (with browser fallback and copied state feedback).

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
  - `tests/Feature/PrivacyToggleTest.php`
  - `tests/Feature/UsernameTest.php`
  - `tests/Feature/FeedPosts/FeedPostsFeatureTest.php --filter=saved`
  - `tests/Feature/ProfileTest.php --filter="pin|pinned|profile posts tab shows pinned"`
  - `tests/Feature/FeedPosts/FeedPostsFeatureTest.php --filter="share|feed_post_card_renders_share_action"`
