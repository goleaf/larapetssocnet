# Testing

## File uploads
- Use `Storage::fake('public')`
- Use `UploadedFile::fake()->image(...)` and `->create(...)`
- Assert media collection counts and public disk files
- Keep shared-hosting path tests current when public assets, Vite output, or `.htaccess` protections change.
- For deploy or production auth fixes, verify both the user-facing subdomain flow and the remote Laravel log tail; compare timestamps so stale transport errors are not mistaken for new failures.

## Observers
- Use `withoutEvents()`/`Event::fake()` for isolated unit tests where needed.

## Social actions (Pest)
- Use `actingAs()` for authenticated follow/request flows.
- Assert rows with `assertDatabaseHas()` / `assertDatabaseMissing()`.
- Use `Notification::fake()` + `assertSentTo()` for follow notifications.
- Verify counter cache accuracy after follow/unfollow/approve/reject.
- Add blocked-user denial tests for follow and related social actions.
- Add idempotency tests (repeat follow should not create duplicates).

## Assertion quality
- Do not use no-op truth assertions that only prove the test runner executes.
- Remove starter-template comments such as `A basic test example.` when replacing scaffold tests.
- Prefer semantic response assertions such as `assertOk()`, `assertForbidden()`, `assertNotFound()`, and `assertInvalid()` over raw common status codes.
- Keep `only()`, `skip()`, and `todo()` out of committed tests unless the team explicitly accepts a temporary disabled test.

## Controller coverage and local hooks
- Every changed concrete controller should have at least one related feature test unless the change is purely unreachable cleanup.
- Prefer route-level assertions that prove the user-visible behavior, authorization boundary, validation branch, and persistence effect.
- Run `php scripts/controller-test-map.php --changed` before delivery when controller files changed.
- Install local hooks with `bash scripts/install-git-hooks.sh` so changed-controller mapping, Pint, Composer validation, feature/unit tests, SCSS lint, and Vite build run before repository history receives the change.

## Testing Visibility Rules
- Test each visibility level from the relevant viewer perspectives:
  - guest redirect to login for application page routes
  - owner (self)
  - accepted follower
  - non-follower
- Recommended matrix loop:
  - visibility in `[public, followers, private]`
  - viewer in `[guest redirect, self, follower, non-follower]`
- Use `assertRedirect(route('login'))` for guests, `assertOk()` for allowed authenticated access, and `assertForbidden()`/`assertNotFound()` for denied authenticated access.
- For feed/profile visibility, add `assertSee()` / `assertDontSee()` checks per viewer role.

## TESTING POLYMORPHIC RELATIONSHIPS

When asserting polymorphic rows, assert both `*_type` and `*_id` explicitly.
If a morph map exists, use mapped type; otherwise use full class name.

```php
assertDatabaseHas('reactions', [
    'reactable_type' => Post::class,
    'reactable_id' => $post->id,
]);
```

## TESTING TOGGLE FLOWS

For toggle endpoints/actions, call twice and assert state returns to original.

## TESTING THROTTLED ROUTES

In most feature tests, disable throttle middleware to test behavior directly.
Add a dedicated rate-limit test per throttled endpoint.

## PET FOLLOW TESTING

- Test follow/unfollow toggle and idempotency.
- Assert `followers_count` counter behavior.

## ADOPTION TRANSITION TESTING

- Test allowed transitions succeed.
- Test invalid transitions fail with validation/exception.

## GROUP MEMBERSHIP ROLE TESTING

- Test join states (`accepted`/`pending`).
- Test approve/reject flow.
- Test role promotion/demotion rules.
- Test owner leave guard.

## HEALTH LOG TESTING

- Test CRUD ownership boundaries.
- Test type filtering and pagination.
- Test upcoming and urgent reminder windows.

## SVG CHART TESTING

- Assert chart contains `<svg` and `<polyline` when sufficient points exist.
- Assert `null` when points are insufficient.
