# Post Types

Types are auto-detected in `PostService`:
- no media => `text`
- photos => `photo`
- video => `video`

Type is immutable after creation.

## Composer Pet Tags

Use the shared Livewire `posts.composer` pet tagging controls instead of raw multi-selects. The normal composer shows a toolbar "Tag a pet" dropdown listing owned/postable pets by name with avatar and species badge, and selected pets render as removable chips below the editor. Pet-profile composer contexts must pass a fixed pet through `contextType=pet-profile` and `contextId` or `fixedPetId` / `lockPetTags`; that hides the tagging dropdown and keeps the pre-tagged pet immutable so the post remains on that pet profile.

## Composer Location Tags

Use the shared Livewire `posts.composer` location controls instead of raw location, latitude, and longitude fields. The toolbar pin toggles a compact location picker below the editor, bound with `wire:model.live.debounce.400ms` to server-side `LocationAutocompleteService` suggestions. Suggestions must return a display label plus separate latitude and longitude values, and selecting one stores `locationDisplayText`, `locationLat`, and `locationLng` while rendering a removable location chip below the editor. The compass action must use browser geolocation only to collect coordinates; reverse geocoding still runs through the server service so API keys and provider details stay off the client.

## Composer Mood Picker

Use the shared Livewire `posts.composer` mood controls instead of raw select fields. The toolbar mood button opens a compact emoji grid backed by `PostMood::DISPLAY`; selecting a mood stores the existing mood value, closes the popover, and renders an italic "feeling {emoji} {label}" indicator below the editor with a remove button. Keep mood labels and emoji centralized in `PostMood` so composer, post card, and validation behavior stay aligned.

## Composer Visibility

The shared Livewire `posts.composer` uses a compact toolbar visibility dropdown instead of a full form select. Mount should initialize `selectedVisibility` from the passed prop when present, otherwise from the user's stored visibility preference on the user record; selecting a different post visibility must update only composer state and must not persist a new account default. The dropdown renders radio-card options for Public, Followers, Friends, and Only me, and the Only me state shows the explicit warning: "Only you will see this post".
