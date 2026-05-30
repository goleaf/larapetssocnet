# Media Uploads

All uploads go through Spatie MediaLibrary.

- Never use `$file->store()`.
- Never persist storage paths directly in post columns.
- Use `addMedia()->toMediaCollection()`.
- Define collections in `registerMediaCollections()`.
- Define conversions in `registerMediaConversions()`.

## Post Media (StorePostRequest)
- Max files: 10 attachments per post.
- Images and videos may be mixed in one post.
- Max image size: 10MB per file.
- Max video size: 100MB per file.
- Image types: `jpg`, `jpeg`, `png`, `gif`, `webp`.
- Video types: `mp4`, `mov`.
- The Livewire post composer attachment strip loads Sortable.js from the approved CDN on demand and persists the final order through `post_media.order`.
- Livewire temporary uploads create `post_media` placeholder rows inside the post creation transaction with `processing_status=processing` and the temporary path as `file_path`; `PostMediaProcessingService` moves the file through Spatie MediaLibrary, updates the same row to `processing_status=ready`, and marks it `failed` if the temporary file cannot be found. Keep ordered post media hydration on the `post_media(post_id, order)` index.
- Finalized post media uses the post-specific Spatie path generator `DateBasedMediaPathGenerator`, storing originals and derivatives under `posts/YYYY/MM/DD/{media_id}/` so large media collections remain date partitioned.
- Image attachments should expose the composer Canvas editor before upload finalization. Edits are client-side only: crop, rotate 90 degrees in either direction, flip horizontally/vertically, and brightness/contrast adjustments replace the attachment preview and re-upload the edited PNG to Livewire temporary storage.
- Missing image alt text is encouraged, not required. The composer shows a non-blocking amber reminder and can highlight only image thumbnails missing alt text; never block post submission on alt text completeness.

Post collections:
- `photos` (multiple)
- `videos` (multiple)

Post image conversions:
- `thumb` 150x150 crop webp q80
- `medium` width 800 webp q85
- `large` width 1200 webp q90

Profile Posts media-only mode reads media through `Post::mediaItemsForDisplay()` and filters with `Post::containingMedia()` so both legacy `post_media` rows and Spatie collections remain visible without PHP-side post filtering. Profile Photos treats `post_media` as the canonical thumbnail cursor because current post uploads write a `post_media` row beside each Spatie media item: load 30 image rows at a time through `Post::profilePhotoMediaPage()`, append only media IDs to Livewire state, and rehydrate through `Post::profilePhotoMediaByIds()` before rendering the square two-column mobile/tablet and three-column desktop grid. Opening a profile photo must keep using the same loaded, visibility-scoped photo collection for the Livewire lightbox so navigation never reveals hidden post media.

## Profile Media
The profile edit modal uses two distinct Livewire upload panels for owner media: avatar and cover photo. Keep them in one responsive grid that stacks on mobile and uses two columns from desktop widths, with separate previews, validation errors, and removal controls for each media collection.

Avatar uploads should use the circular drop-zone interface in the profile edit modal: clicking or dropping onto the circle opens/selects the file, FileReader renders the circular preview before the Livewire temporary upload starts, and Alpine validates JPEG/PNG/WEBP plus a 3MB maximum before upload. The server-side profile validators must keep the same 3MB avatar limit. Cover uploads should use the 3:1 rectangular drop-zone interface, render the current cover or username gradient fallback, validate at least 1200x400 pixels and 5MB before upload, then show the inline vertical drag crop control after the Livewire temporary upload finishes so `cover_photo_position` is saved with the cover.

Profile avatar and cover saves must move Livewire temporary uploads into permanent Spatie Media Library collections and leave avatar/cover image resizing plus optimizer work on the configured Media Library conversion queue. Do not mark profile media conversions as non-queued.

Profile Wrapped share images are generated analytics artifacts, not user uploads. Store the queued PNG output on the public disk under `profile-wrapped/{year}/user-{id}.png`, persist only the generated path on `profile_wrapped_summaries`, and do not attach these cards to user media collections.

## Pet Media
Pet media uses public disk.

Collections:
- `avatar` (single file)
- `cover` (single file)
- `gallery` (multiple)

Conversions:
- `avatar_thumb` 80x80
- `avatar_small` 150x150
- `avatar_medium` 400x400
- `gallery_thumb` 150x150
- `gallery_medium` width 800

Gallery limits are configured via `config/pets.php`.
