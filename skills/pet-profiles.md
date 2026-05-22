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
- Profile Pets tab queries must keep visibility in `Pet::visibleTo($viewer)`, eager-load pet media, and annotate `viewer_is_following` in SQL before rendering cards.

## Profile Tab Cards
- The profile Pets tab is a lazy nested Livewire component mounted only when the Pets tab is active.
- Render a responsive card grid: one column on mobile, two on tablet, three on desktop.
- Each card shows a square pet photo, name, species/breed subtitle, dynamic `age_formatted`, cached follower count, and an authorized optimistic Follow Pet action only for authenticated viewers who do not already follow the pet and do not own it.
- Profile card Follow Pet actions keep state local to the card: Alpine immediately updates the count and switches the button to "Following", while a renderless Livewire action persists through `PetFollowService` and returns the canonical count without re-rendering the parent profile component.
- Profile owners with pets see an Add Pet card as the first grid item. Owners with no pets see an illustrated onboarding empty state with an Add Your First Pet button. Visitors with no visible pets see only a simple No Pets Yet empty state.
- Owner add-pet actions open an on-page Livewire modal, validate the same core Feature 3 pet fields, persist through `CreatePetAction`, refresh the lazy grid, and dispatch a parent profile event so the Pets tab counter updates without navigation.

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
