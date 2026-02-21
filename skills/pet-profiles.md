# Pet Profiles

Pet profiles are user-owned sub-entities.

- One user can own multiple pets.
- Pet public page: `/pets/{slug}`.
- Slug format: pet name + owner username (e.g. `buddy-john`).
- Slug is immutable after creation.

## Core fields
- `species`: `dog|cat|bird|fish|rabbit|hamster|reptile|other`
- `breed`: nullable free text
- `gender`: `male|female|unknown`
- `size`: `small|medium|large|xlarge` (optional)
- `date_of_birth`: date nullable
- `age_text`: free text fallback when DOB unknown
- `bio`: max 500, sanitized like user bio
- `is_deceased`: boolean (show Rainbow Bridge badge)

## Privacy
- Pet profile visibility inherits owner account privacy.

## Media
- Pet implements MediaLibrary.
- `avatar` collection: single file.
- `gallery` collection: multiple files, max 30.
