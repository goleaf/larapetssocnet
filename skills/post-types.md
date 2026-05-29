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

## Composer Scheduled Posting

Use the shared Livewire `posts.composer` scheduled-post controls instead of raw `datetime-local` fields. The toolbar clock opens an inline month calendar plus hour/minute selectors in 15-minute increments, disables past dates/times in the browser, and calls `setScheduledPost()` with a UTC ISO timestamp generated from the viewer's local selection. Scheduled posts render a removable "Scheduled for ..." indicator below the editor, change the submit button to "Schedule", persist as `PostStatus::Scheduled`, and must not dispatch feed fan-out or mention notifications until `PublishScheduledPostJob` publishes them.

The `posts:publish-scheduled` command runs every minute, uses the database cache lock `posts:publish-scheduled-command`, selects at most 100 due posts through the `posts_status_scheduled_publish_at_index`, and dispatches one queued job per post. Keep the per-post job guarded by `posts:publish-scheduled:{postId}` and idempotent by returning early unless the post is still scheduled and due.

## Composer Link Previews

The shared Livewire `posts.composer` detects pasted HTTP(S) URLs in the contenteditable editor and calls `queueLinkPreviewFetch()` after a one-second debounce. The Livewire action must dispatch `FetchLinkPreviewMetadataJob` instead of fetching Open Graph metadata inline. While the job runs, render the composer skeleton with `wire:poll.2s="pollLinkPreviewResult"` against the short-lived cache result; successful results populate `linkPreviewData`, failed results stop loading without blocking submission, and dismissing a preview stores the dismissed URL so it does not immediately reappear.

`FetchLinkPreviewMetadataJob` uses `PostMetadataService` with Guzzle timeout and redirect tracking, rejects localhost/private preview targets, parses Open Graph/canonical metadata server-side, and either caches composer preview results or updates `posts.link_preview` after a post has been created. Posts with an unfetched URL must still be created immediately; the queued job is responsible for filling the JSON preview later. Shared post cards render the preview image when present, capped at 200px high with object-cover.

## Composer Visibility

The shared Livewire `posts.composer` uses a compact toolbar visibility dropdown instead of a full form select. Mount should initialize `selectedVisibility` from the passed prop when present, otherwise from the user's stored visibility preference on the user record; selecting a different post visibility must update only composer state and must not persist a new account default. The dropdown renders radio-card options for Public, Followers, Friends, and Only me, and the Only me state shows the explicit warning: "Only you will see this post".
