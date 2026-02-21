# Testing

## File uploads
- Use `Storage::fake('public')`
- Use `UploadedFile::fake()->image(...)` and `->create(...)`
- Assert media collection counts and public disk files

## Observers
- Use `withoutEvents()`/`Event::fake()` for isolated unit tests where needed.

## Social actions (Pest)
- Use `actingAs()` for authenticated follow/request flows.
- Assert rows with `assertDatabaseHas()` / `assertDatabaseMissing()`.
- Use `Notification::fake()` + `assertSentTo()` for follow notifications.
- Verify counter cache accuracy after follow/unfollow/approve/reject.
- Add blocked-user denial tests for follow and related social actions.
- Add idempotency tests (repeat follow should not create duplicates).

## Testing Visibility Rules
- Test each visibility level from four viewer perspectives:
  - guest
  - owner (self)
  - accepted follower
  - non-follower
- Recommended matrix loop:
  - visibility in `[public, followers, private]`
  - viewer in `[guest, self, follower, non-follower]`
- Use `assertOk()` for allowed access and `assertForbidden()` for denied access.
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
