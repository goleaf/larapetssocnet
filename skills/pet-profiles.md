# Pet Profiles

Pet profiles are user-owned sub-entities.

- One user can own multiple pets.
- Pet profile page: `/pets/{slug}` (route model binding prefers slug) and requires authentication.
- Slug is generated on create from pet name + owner username and is not updated on edit.

## Core Fields
- `name`
- `species`: `dog|cat|bird|fish|rabbit|hamster|reptile|other`
- `breed`: nullable free text
- `gender`: `male|female|unknown`
- `size`: `small|medium|large|xlarge` (optional)
- `date_of_birth`: date nullable
- `age_text`: free text fallback when DOB unknown
- `bio`: max 500, sanitized via `ContentService`
- `personality_tags`: array (normalized by `PersonalityTagService`)
- `is_public`: boolean visibility flag
- `is_deceased`: boolean (used for Rainbow Bridge badge)
- `is_adoptable`: boolean (drives adoption listing eligibility)

## Privacy
- Pet visibility is controlled by `is_public` and enforced via `PetVisibilityService` and `Pet::visibleTo()` after the viewer is authenticated.

## Media
Pet uses Spatie MediaLibrary (public disk).

Collections:
- `avatar` (single file)
- `cover` (single file)
- `gallery` (multiple files)

Conversions:
- `avatar_thumb` 80x80
- `avatar_small` 150x150
- `avatar_medium` 400x400
- `gallery_thumb` 150x150
- `gallery_medium` width 800

Gallery limits are configured via `config/pets.php` (`pets.gallery.max_upload`, `pets.gallery.max_file_size_kb`).
