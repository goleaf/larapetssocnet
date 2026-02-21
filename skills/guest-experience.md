# Guest Experience

## Guests Can
- Browse Explore.
- View public posts, profiles, pet pages, hashtags.
- Search Explore.
- Browse public groups, marketplace, and events.

## Guests Cannot
- React, save, follow, comment, message.
- Access private profiles or followers-only posts.
- Access `/feed`.

## CTA Pattern
- Never block content with login walls.
- Gate only interaction.
- On gated action click: show tooltip `Log in to [action]`.
- Show dismissible Explore CTA banner for guests.
- Store dismissal in `localStorage` key `explore_cta_dismissed`.

## Never
- Do not redirect guest from public post views to login.
- Do not blank Explore for guests.
