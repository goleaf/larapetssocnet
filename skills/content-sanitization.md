# Content Sanitization

Store both:
- `body` raw input
- `body_html` sanitized output

Never render raw body in Blade.
Render `{!! $post->body_html !!}` only.
