# Pet Profiles

Pet profiles are user-owned sub-entities.

- One user can own multiple pets.
- Pet profile page: `/pets/@{slug}` (route model binding resolves slug only and strips the `@` prefix) and requires authentication. Legacy `/pets/{slug}` links redirect to the canonical route.
- Slug is generated on create from pet name + a six-character random suffix and is not updated on edit.

## Core Fields
- `name`: normalized with `Str::squish` before validation; whitespace-only names must fail as required.
- `species_id`: nullable normalized lookup reference while legacy `species` remains synchronized for compatibility.
- `breed_id`: nullable normalized lookup reference.
- `breed_description`: nullable display fallback for Mixed/Unknown/free-text breed labels.
- `gender`: `male|female|unknown`
- `size`: `small|medium|large|xlarge` (optional)
- `date_of_birth`: date nullable
- `birth_year`: approximate year when exact DOB is unknown
- `age_text`: free text fallback when DOB unknown
- `bio`: max 500, sanitized via `ContentService`
- `personality_tags`: array (normalized by `PersonalityTagService`)
- `is_public`: boolean visibility flag
- `is_deceased`: boolean (used for Rainbow Bridge badge)
- `is_adoptable`: boolean (drives adoption listing eligibility)

## Privacy
- Pet visibility is controlled by canonical `visibility` (`public`, `followers_only`, `private`) with legacy `is_public` synchronized for compatibility. Pet privacy is independent from the owner's account privacy.
- Visibility is enforced via `PetVisibilityService`, `PetPolicy`, and `Pet::visibleTo()` after the viewer is authenticated.
- Follower-only pet profiles expose the identity shell but keep posts, gallery, and milestones locked until the viewer follows that pet.
- Blocked viewers should receive a not-found pet profile response through policy denial so the pet URL does not confirm profile existence.
- Profile Pets tab queries must keep visibility in `Pet::visibleTo($viewer)`, eager-load pet media, and annotate `viewer_is_following` in SQL before rendering cards.

## Ownership
- Primary ownership stays on `pets.user_id`.
- Co-ownership lives in `pet_owners` with one row per pet/user, canonical `role` values of `owner`, `admin`, `poster`, or `viewer`, `is_primary_owner`, `accepted_at`, and legacy scoped booleans kept synchronized for compatibility.
- Policies must authorize co-owner abilities through `PetPolicy::ROLE_CAPABILITIES`; do not duplicate role checks in controllers, Blade, or Livewire components.
- Pet post attachment validation must allow only the primary owner or an accepted co-owner with the Owner/Admin/Poster capability.
- Co-owner invitations are two-phase records in `pet_owner_invitations`; accept creates the scoped `pet_owners` row, decline leaves no ownership, and stale pending invitations expire through `pets:expire-owner-invitations`.
- Ownership transfers are two-phase records in `pet_ownership_transfers`; the current owner keeps owner capabilities until acceptance, acceptance atomically promotes the proposed owner and demotes the previous owner to Admin, and stale transfers expire through `pets:expire-ownership-transfers`.

## Family and Care
- Explicit pet family links live in `pet_relationships`; creation must write the inverse relationship in the same transaction and must not reveal private pets the actor cannot view.
- Pet health reminders live in `pet_health_reminders`, are managed through `PetPolicy::manageHealth`, and are sent by `pets:send-health-reminders` to the primary owner plus accepted co-owners.

## Milestones
- Pet milestones live in `pet_milestones` and belong to a pet, optional actor user, and optional shared post.
- Timeline reads should order by `occurred_on` and use the `pet_milestones(pet_id, occurred_on)` index.
- When `share_as_post` is enabled, create the post through `CreatePostAction` so post visibility, sanitation, tags, and events follow the normal post pipeline.

## Profile Identity
- The pet show page should present the pet as an independent community identity, not as a shortened user profile.
- Keep the summary focused on identity facts: species/breed, sex, size, dynamic age, species-aware life stage, visibility, personality tags, and memorial state when relevant.
- Species-aware life stages are derived from completed months since `birth_date` or `date_of_birth`; keep this calculation deterministic instead of relying on ambiguous date-diff defaults.
- Visitors may see the public identity, stewardship, QR sharing, adoption availability, and milestone story preview. Owner-only care notes can include latest weight, vaccination, and upcoming care reminders.
- Do not expose health-derived care notes to non-owners; profile visibility and pet policies still decide whether the profile can be loaded at all.

## Sharing and Notifications
- Pet profile QR codes are generated server-side as SVGs through the pet QR routes. Do not add a QR package unless the in-house SVG service stops meeting requirements.
- Pet birthday reminders are sent by `pets:send-birthday-notifications`, scheduled daily at `config('pets.birthday.notification_time')`, and dispatch one `ProcessPetBirthday` queued job per pet whose indexed `birthday_month_day` key, derived from `birth_date` or `date_of_birth`, matches today.
- Birthday jobs create a system-generated post tagged to the pet, notify eligible pet followers in batches, and send co-owner-specific notifications to co-owners who follow the pet.
- Pet health reminders are scheduled daily at `config('pets.health_reminders.notification_time')` and advance each reminder's next due date after notifications are queued.

## Creation UI
- The pet creation surface uses a three-step Alpine wizard for basics, story, and photos/adoption flags.
- Species-to-breed autocomplete is authenticated, debounced, prepends Mixed/Unknown pseudo-options, and prefers `species_id` plus `normalized_name` prefix matching so `breeds(species_id, normalized_name, name)` remains useful.

## Profile Tab Cards
- The profile Pets tab is a lazy nested Livewire component mounted only when the Pets tab is active.
- Render a responsive card grid: one column on mobile, two on tablet, three on desktop.
- Each card shows a square pet photo, name, species/breed subtitle, dynamic `age_formatted`, cached follower count, and an authorized optimistic Follow Pet action only for authenticated viewers who do not already follow the pet and do not own it.
- Profile card Follow Pet actions keep state local to the card: Alpine immediately updates the count and switches the button to "Following", while a renderless Livewire action persists through `PetFollowService` and returns the canonical count without re-rendering the parent profile component.
- Profile owners with pets see an Add Pet card as the first grid item. Owners with no pets see an illustrated onboarding empty state with an Add Your First Pet button. Visitors with no visible pets see only a simple No Pets Yet empty state.
- Owner add-pet actions open an on-page Livewire modal, validate the same core Feature 3 pet fields, persist through `CreatePetAction`, refresh the lazy grid, and dispatch a parent profile event so the Pets tab counter updates without navigation.

## Profile About Summary
- The user profile About tab includes a compact horizontal pet strip after the activity summary. Query it through `Pet::visibleTo($viewer)`, eager-load only avatar media, and render circular avatar/name links to each visible pet profile.
- Keep this strip read-only and lightweight: no follow controls, no per-pet relationship queries, and no private pets for visitors who cannot view them.

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
