# Content Sanitization

Store both:
- `body` raw input
- `body_html` sanitized output

Never render raw body in Blade.
Render `{!! $post->body_html !!}` only.

## Pet and Group Content

- Pet bio sanitization follows same pipeline as user bio.
- Group description sanitization follows post body pipeline.
- Pet care tip body sanitization follows post body pipeline.

## Personality Tags

- Normalize to lowercase.
- Strip everything except alphanumeric + spaces.
- Trim and length-bound each tag.
