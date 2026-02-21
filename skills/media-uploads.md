# Media Uploads

All uploads go through Spatie MediaLibrary.

- Never use `$file->store()`.
- Never persist storage paths directly in post columns.
- Use `addMedia()->toMediaCollection()`.
- Define collections in `registerMediaCollections()`.
- Define conversions in `registerMediaConversions()`.

## Images
- Mime: jpeg/png/webp/gif
- Max per file: 5MB
- Max files: 5
- Conversions:
  - `thumb` 150x150 crop webp q80
  - `medium` width 800 webp q85
  - `large` width 1200 webp q90

## Videos
- Mime: mp4/mov/webm
- Max file: 50MB
- Max files: 1
- No conversion, serve original.

## PET MEDIA COLLECTIONS

Pet media uses public disk.

### Avatar
- single file
- max 5MB
- conversions:
  - `thumb` 80x80 webp
  - `small` 150x150 webp
  - `medium` 400x400 webp

### Gallery
- multiple files, max 30
- conversions:
  - `thumb` 150x150 webp
  - `medium` width 800 webp
