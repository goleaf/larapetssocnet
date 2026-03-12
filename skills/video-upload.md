# Video Upload Rules

- Max one video per post.
- Accepted extensions (validation): `mp4`, `mov`.
- Max size: 20MB per file.
- No server-side transcoding.
- Always store using Spatie MediaLibrary in `videos` collection.
- `videos` collection is `singleFile()`.
- Do not register video conversions.
- Do not use `Storage::put()` for videos.

## Validation
- `StorePostRequest` allows video uploads via `media` or `video` input.
- Mutual exclusivity (video vs photos) is enforced in `withValidator()`.
- Only one video allowed.

## Post Type
- If a video is uploaded, post `type` is `video` (resolved in `PostService`).

## Player UI
Use native HTML5 video:
- `<video controls playsinline preload="metadata">`
- Width `100%`
- Max height `500px` (layout controlled by the post card)
