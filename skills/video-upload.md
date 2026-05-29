# Video Upload Rules

- Multiple videos can be attached to one post as part of the 10-attachment post media limit.
- Accepted extensions (validation): `mp4`, `mov`.
- Max size: 100MB per file.
- No server-side transcoding.
- Always store using Spatie MediaLibrary in `videos` collection.
- `videos` collection allows multiple files.
- Do not register video conversions.
- Do not use `Storage::put()` for videos.

## Validation
- `StorePostRequest` allows video uploads via `media` or `video` input.
- Mixed image/video posts are allowed.
- Total post attachments are capped at 10.

## Post Type
- If a video is uploaded, post `type` is `video` (resolved in `PostService`).

## Player UI
Use native HTML5 video:
- `<video controls playsinline preload="metadata">`
- Width `100%`
- Max height `500px` (layout controlled by the post card)
