# Guest Experience

## Guests Can
- View authentication pages (`/login`, `/register`, password reset, email verification).
- View system pages that intentionally avoid authentication, such as `/banned`.
- Reach the root URL, which redirects to login when unauthenticated.

## Guests Cannot
- Browse Explore, search, feeds, profiles, posts, pets, hashtags, adoption, marketplace, groups, events, tips, messages, notifications, or settings.
- React, save, follow, comment, message.
- Access direct URLs for application content before login.

## Auth Gate Pattern
- Prefer route middleware (`auth`, `banned`, `track_last_seen`) for application pages.
- Guests should redirect to the `login` route instead of rendering partial content.
- Keep auth pages and intentional system pages outside the authenticated application route group.

## Never
- Do not reintroduce guest browsing for Explore or public content pages without an explicit product-policy change.
- Do not replace middleware-based access control with ad hoc controller checks.
