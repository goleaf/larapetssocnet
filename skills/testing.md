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
