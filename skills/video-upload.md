# Video Upload Rules

- Max one video per post.
- Accepted extensions: `mp4`, `mov`, `webm`.
- Accepted MIME types (validated by file header/finfo):
  - `video/mp4`
  - `video/quicktime`
  - `video/webm`
- Max size: 50MB.
- No server-side transcoding.
- Always store using Spatie MediaLibrary in `videos` collection.
- `videos` collection must be `singleFile()`.
- Do not register video conversions.
- Do not use `Storage::put()` for videos.
- Use `addMedia(...)->toMediaCollection('videos')` only.

## Validation

In `StorePostRequest`:

- `video` must be prohibited if photos are uploaded.
- `photos` must be prohibited if a video is uploaded.
- Enforce these with `Rule::prohibitedIf(...)` in `rules()`.

Validation messages should include:

- Accepted formats guidance.
- 50MB max size guidance.
- Mutual exclusivity guidance.

## Post Type

- If a video is uploaded, post `type` is `video`.

## Player UI

Use native HTML5 video:

- `<video controls playsinline preload="metadata">`
- Width `100%`
- Max height `500px`

Poster:

- If mixed upload UX is present, first photo can be a poster.
- Otherwise fallback to native browser behavior.

## Service Ownership

`VideoService` owns:

- file header MIME validation
- attach/replace video transaction
- video delete flow
- custom properties (`filesize`, `original_name`, optional duration)
