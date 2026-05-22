# Media Uploads

All uploads go through Spatie MediaLibrary.

- Never use `$file->store()`.
- Never persist storage paths directly in post columns.
- Use `addMedia()->toMediaCollection()`.
- Define collections in `registerMediaCollections()`.
- Define conversions in `registerMediaConversions()`.

## Post Media (StorePostRequest)
- Max files: 5 images or 1 video.
- Max file size: 20MB per file.
- Image types: `jpg`, `jpeg`, `png`, `gif`, `webp`.
- Video types: `mp4`, `mov`.
- Videos cannot be uploaded together with photos.

Post collections:
- `photos` (multiple)
- `videos` (singleFile)

Post image conversions:
- `thumb` 150x150 crop webp q80
- `medium` width 800 webp q85
- `large` width 1200 webp q90

Profile Posts media-only mode reads media through `Post::mediaItemsForDisplay()` and filters with `Post::containingMedia()` so both legacy `post_media` rows and Spatie collections remain visible without PHP-side post filtering. Profile Photos treats `post_media` as the canonical thumbnail cursor because current post uploads write a `post_media` row beside each Spatie media item: load 30 image rows at a time through `Post::profilePhotoMediaPage()`, append only media IDs to Livewire state, and rehydrate through `Post::profilePhotoMediaByIds()` before rendering the square two-column mobile/tablet and three-column desktop grid. Opening a profile photo must keep using the same loaded, visibility-scoped photo collection for the Livewire lightbox so navigation never reveals hidden post media.

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
