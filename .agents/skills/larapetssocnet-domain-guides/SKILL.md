---
name: larapetssocnet-domain-guides
description: Use when working on this app's domain-specific Laravel features, including feeds, posts, comments, reactions, follows, pet profiles, adoptions, groups, messaging, notifications, privacy, visibility, moderation, hashtags, search, listings, and health logs. Read the matching project guide from the repo's skills folder before changing code.
---

# Larapetssocnet Domain Guides

Use this skill as the project-specific router for domain behavior. Keep context small: open only the guide files that match the feature you are touching.

Also activate other matching skills when relevant:
- `using-laravel-superpowers` for Laravel workflow guardrails
- `pest-testing` for tests
- Read [form-requests.md](../../../skills/form-requests.md) for request validation
- Read [policies.md](../../../skills/policies.md) and [security.md](../../../skills/security.md) for protected actions

## Domain Lookup

### Feed, posts, and content flows

- Feed and ranking: [feed-architecture.md](../../../skills/feed-architecture.md), [feed-filtering.md](../../../skills/feed-filtering.md), [trending-algorithm.md](../../../skills/trending-algorithm.md), [explore-architecture.md](../../../skills/explore-architecture.md)
- Post behavior: [post-types.md](../../../skills/post-types.md), [post-visibility.md](../../../skills/post-visibility.md), [saved-posts.md](../../../skills/saved-posts.md), [pin-post.md](../../../skills/pin-post.md), [content-service.md](../../../skills/content-service.md)
- Comments and reactions: [comments.md](../../../skills/comments.md), [threaded-comments.md](../../../skills/threaded-comments.md), [reactions.md](../../../skills/reactions.md)

### Social graph and community features

- User follows and privacy flows: [relations.md](../../../skills/relations.md), [notifications.md](../../../skills/notifications.md), [visibility-rules.md](../../../skills/visibility-rules.md), [visibility-enforcement.md](../../../skills/visibility-enforcement.md), [policies.md](../../../skills/policies.md)
- Pet follows: [pet-follow.md](../../../skills/pet-follow.md)
- Groups and membership: [groups.md](../../../skills/groups.md), [group-membership.md](../../../skills/group-membership.md)
- Messaging and reporting: [reporting.md](../../../skills/reporting.md)

### Pets, adoption, and profile data

- Pet profile behavior: [pet-profiles.md](../../../skills/pet-profiles.md), [personality-tags.md](../../../skills/personality-tags.md)
- Adoption and listings: [adoption.md](../../../skills/adoption.md)
- Health and care: [health-logs.md](../../../skills/health-logs.md), [pet-care-tips.md](../../../skills/pet-care-tips.md)

### Discovery, search, and hashtags

- Search and discovery: [search-architecture.md](../../../skills/search-architecture.md), [hashtag-pages.md](../../../skills/hashtag-pages.md), [hashtag-extraction.md](../../../skills/hashtag-extraction.md)

### Data access, services, and persistence

- Service-layer patterns: [laravel.md](../../../skills/laravel.md), [service-pattern.md](../../../skills/service-pattern.md), [orm.md](../../../skills/orm.md), [eloquent-patterns.md](../../../skills/eloquent-patterns.md)
- Query and loading discipline: [query-optimization.md](../../../skills/query-optimization.md), [eager-loading-patterns.md](../../../skills/eager-loading-patterns.md), [counters.md](../../../skills/counters.md), [pivot-models.md](../../../skills/pivot-models.md), [sqlite.md](../../../skills/sqlite.md)
- Events and exception behavior: [events-listeners.md](../../../skills/events-listeners.md), [post-observer.md](../../../skills/post-observer.md), [exception-handling.md](../../../skills/exception-handling.md)

### Media and sharing

- Uploads and media handling: [media-uploads.md](../../../skills/media-uploads.md), [video-upload.md](../../../skills/video-upload.md), [clipboard-share.md](../../../skills/clipboard-share.md)

If multiple guides apply, start with the one that defines behavioral rules, then read the UI or implementation-pattern guide.
