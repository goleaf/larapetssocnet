# Testing

## File uploads
- Use `Storage::fake('public')`
- Use `UploadedFile::fake()->image(...)` and `->create(...)`
- Assert media collection counts and public disk files

## Observers
- Use `withoutEvents()`/`Event::fake()` for isolated unit tests where needed.
