# Post Types

Types are auto-detected in `PostService`:
- no media => `text`
- photos => `photo`
- video => `video`

Type is immutable after creation.

## Composer Entry Points

Use the shared Livewire `posts.composer` everywhere a user creates or edits normal post content. The feed may mount it inline, while the owner profile Posts tab and the authenticated mobile floating action button mount it in modal mode without navigating away from the current page. Entry points should pass context props instead of branching into separate form implementations.

## Composer Mentions

The shared composer highlights hashtags and mentions inside the contenteditable editor with Alpine while keeping the raw `textContent` synchronized to Livewire. Active mention text must also call the composer's debounced server-backed `searchMentionSuggestions()` action, query `users.username` by prefix, exclude the current viewer, cap results to five accounts, and insert the selected username back into the editor without trusting client-rendered spans as submission data.

## Composer Pet Tags

Use the shared Livewire `posts.composer` pet tagging controls instead of raw multi-selects. The normal composer shows a toolbar "Tag a pet" dropdown listing owned/postable pets by name with avatar and species badge, and selected pets render as removable chips below the editor. Pet-profile composer contexts must pass a fixed pet through `contextType=pet-profile` and `contextId` or `fixedPetId` / `lockPetTags`; that hides the tagging dropdown and keeps the pre-tagged pet immutable so the post remains on that pet profile.

## Composer Location Tags

Use the shared Livewire `posts.composer` location controls instead of raw location, latitude, and longitude fields. The toolbar pin toggles a compact location picker below the editor, bound with `wire:model.live.debounce.400ms` to server-side `LocationAutocompleteService` suggestions. Suggestions must return a display label plus separate latitude and longitude values, and selecting one stores `locationDisplayText`, `locationLat`, and `locationLng` while rendering a removable location chip below the editor. The compass action must use browser geolocation only to collect coordinates; reverse geocoding still runs through the server service so API keys and provider details stay off the client.

## Composer Mood Picker

Use the shared Livewire `posts.composer` mood controls instead of raw select fields. The toolbar mood button opens a compact emoji grid backed by `PostMood::DISPLAY`; selecting a mood stores the existing mood value, closes the popover, and renders an italic "feeling {emoji} {label}" indicator below the editor with a remove button. Keep mood labels and emoji centralized in `PostMood` so composer, post card, and validation behavior stay aligned.

## Composer Scheduled Posting

Use the shared Livewire `posts.composer` scheduled-post controls instead of raw `datetime-local` fields. The toolbar clock opens an inline month calendar plus hour/minute selectors in 15-minute increments, disables past dates/times in the browser, and calls `setScheduledPost()` with a UTC ISO timestamp generated from the viewer's local selection. Scheduled posts render a removable "Scheduled for ..." indicator below the editor, change the submit button to "Schedule", persist as `PostStatus::Scheduled`, and must not dispatch feed fan-out or mention notifications until `PublishScheduledPostJob` publishes them.

The `posts:publish-scheduled` command runs every minute, uses the database cache lock `posts:publish-scheduled-command`, selects at most 100 due posts through the `posts_status_scheduled_publish_at_index`, and dispatches one queued job per post. Keep the per-post job guarded by `posts:publish-scheduled:{postId}` and idempotent by returning early unless the post is still scheduled and due. `FeedFanOutJob` must also check `posts.is_fanned_out` under its fan-out lock and set it after successful fan-out so duplicate scheduled-publication dispatches are no-ops.

## Composer Draft Autosave

Use the shared Livewire `posts.composer` draft lifecycle instead of page-local draft forms. Alpine tracks dirty composer state and triggers the debounced Livewire `autosaveDraft()` action every 10 seconds only when there are unsaved changes; the service upserts one serialized state payload per user through the unique `post_drafts_user_id_unique` index. Persist `post_drafts.state_hash` and skip the upsert entirely when the normalized state hash matches the existing draft so timer ticks do not write unchanged content. Opening the composer must show a "You have an unsaved draft from ..." banner with Resume draft and Discard actions instead of restoring content automatically. Successful post submission and confirmed composer cancellation clear the user draft.

## Post Creation Action

`CreatePostAction` accepts only a `PostCreationInput` DTO and returns `PostCreationResult`; it must not accept arrays, `Request` objects, or Livewire component state directly. Controllers, Livewire components, jobs, and services build the DTO from server-validated input before calling the action. Keep synchronous writes inside the action transaction: post row, processing-state temporary media placeholders, pet tags, hashtag sync, and mention sync. Dispatch temporary media jobs, link-preview jobs, and feed fan-out after the transaction has committed; published posts fan out only through `FeedFanOutJob`.

## Composer Templates and Writing Assists

Post templates are user-scoped reusable composer text stored in `post_templates` with `user_id`, `name`, and `template_text`; keep the per-user cap at 20. The shared composer owns apply/save/rename/delete flows from the toolbar footer and must never expose another user's templates.

The shared composer also shows an Alpine word count beside the character counter, non-blocking missing-alt-text reminders for image attachments, and a dismissible performance prediction from `PostPerformancePredictionService` after the user pauses composing. Performance predictions must use only the author's own published post counter caches and should return nothing until the author has at least 10 published posts.

## Composer Submission Feedback

The shared Livewire `posts.composer` must disable the composer surface during `submit` / `confirmDuplicateAndSubmit`, show spinner text on the submit button, and avoid clearing form state until `CreatePostAction` succeeds. Duplicate-detection results open a modal with Post anyway and Go back actions; Go back keeps the typed content and clears only duplicate state. Validation failures keep the composer open, set field errors on the Livewire error bag, and dispatch `post-submission-failed` so Alpine scrolls to the first `[data-composer-error]`.

Successful submissions dispatch `post-composer-reset`, a rich `post-created` browser event containing `composerId`, `mode`, `status`, `postId`, author/body display data, and toast text, then dispatch `toast-message`. Inline composers collapse after their own matching `post-created` event, modal composers fade out through `modalOpen = false`, profile Posts tab composer shells close themselves, and feed surfaces listen for published `post-created` events to prepend a highlighted optimistic post card. Scheduled posts show a scheduled-success toast but should not be prepended to normal feeds until publication.

Post deletion from the shared post card is a Livewire-confirmed flow. The owner menu opens a modal with the first 150 characters and first media preview, then confirmation dispatches `post-delete-requested` for optimistic removal, soft-deletes the post immediately in the confirming request, and queues `DeletePostCascadeJob` for counter/feed/media cleanup; saved rows are preserved so saved pages can show deleted placeholders.

## Reposts and Quote Posts

Use the shared Livewire `posts.share-menu` from `x-post-card` for all post-card sharing. The menu renders as a mobile bottom sheet and desktop popover with Repost, Quote post, and Copy link. Repost is a single-tap action that creates a new post for the actor with `original_post_id` set, no body content, normal feed fan-out, and a share-counter increment on the original post. Quote post opens the shared composer in modal mode with `quotePostId`; the composer renders the original post preview below the editor and creates the new post with `quote_post_id` plus the viewer's commentary. Copy link remains a clipboard action with fallback, but it should call the share tracking action so counters and analytics stay consistent.

## Post Analytics

Use the shared Livewire `posts.analytics-trigger` from `x-post-card` for owner-only post analytics. It must authorize with `PostPolicy::viewAnalytics`, load metrics through `PostAnalyticsService`, and render a compact modal with counter-cache values for views, reactions, comments, shares, estimated reach, reaction breakdowns, and a server-side SVG comparison chart.

Count views through `RecordPostViewAction` only when a post card renders in `feed` or `profile` context for an authenticated non-author. Pass explicit non-counting contexts for post detail, explore/hashtag discovery, group feeds, and saved-post pages.

## Composer Edit Mode

Use the shared Livewire `posts.composer` for editing by mounting it in modal mode with `editPostId`. Edit mode must hydrate body text, existing media previews, pet chips, location, mood, visibility, and link preview state from the stored post, show the "Editing post" banner, disable draft autosave, and submit through `UpdatePostAction` rather than `CreatePostAction`. Post edits are limited to 24 hours from `posts.created_at`; enforce the window in the post-card menu, `PostPolicy`, and `UpdatePostAction`. Successful edits dispatch `post-updated` with the post ID and `toast-message` with "Post updated.", then close the modal. Mention notifications after an edit must be sent only for newly added mentions.

## Composer Link Previews

The shared Livewire `posts.composer` detects pasted HTTP(S) URLs in the contenteditable editor and calls `queueLinkPreviewFetch()` after a one-second debounce. The Livewire action must dispatch `FetchLinkPreviewMetadataJob` instead of fetching Open Graph metadata inline. While the job runs, render the composer skeleton with `wire:poll.2s="pollLinkPreviewResult"` against the short-lived cache result; successful results populate `linkPreviewData`, failed results stop loading without blocking submission, and dismissing a preview stores the dismissed URL so it does not immediately reappear.

`FetchLinkPreviewMetadataJob` uses `PostMetadataService` with Guzzle timeout and redirect tracking, rejects localhost/private preview targets, parses Open Graph/canonical metadata server-side, and either caches composer preview results or updates `posts.link_preview` after a post has been created. Posts with an unfetched URL must still be created immediately; the queued job is responsible for filling the JSON preview later. Shared post cards render the preview image when present, capped at 200px high with object-cover.

## Composer Visibility

The shared Livewire `posts.composer` uses a compact toolbar visibility dropdown instead of a full form select. Mount should initialize `selectedVisibility` from the passed prop when present, otherwise from the user's stored visibility preference on the user record; selecting a different post visibility must update only composer state and must not persist a new account default. The dropdown renders radio-card options for Public, Followers, Friends, and Only me, and the Only me state shows the explicit warning: "Only you will see this post".
