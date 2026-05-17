# Features

This file is the project feature index for agent-driven work.

## Maintenance Rule

After every implementation prompt, update this file when feature status, scope, implementation notes, or feature prompt guidance changes. Keep entries concise and aligned with the actual repository, not an idealized future architecture.

The end-of-prompt close-out is:

1. Update all affected Markdown files.
2. Update `FEATURES.md` for feature-level changes.
3. Update `CHANGELOG.md` under `Unreleased`.
4. Check `git status --short`.
5. Stage only intended task files.
6. Commit when git delivery is requested or the work is ready for repository history.
7. Push only when explicitly requested.

## Current Feature Guide Status

Feature prompts should be reconciled with the current Laravel 13, Livewire 4, Tailwind 4, Blade, Alpine, controller/service, SQLite, and shared-hosting structure before implementation.

- Feature 1: Authentication and registration guidance must match the existing Breeze/controller/Form Request flow unless explicitly refactored.
- Feature 2: User profile guidance must match the existing public profile controller, Blade tabs, follow/request, privacy, and media flows.
- Feature 3: Pet profile guidance must match the existing pet controller, pet followers, media, gallery, health, adoption, and visibility flows.
- Feature 4: News feed guidance must match the existing feed controller, cursor pagination, follow graph, pet follow graph, Blade post cards, and implemented `source=people|pets` plus `type=text|photo|video` filters.
- Feature 5: Posts guidance must match the existing post controllers, actions, validation, media limits, visibility, tagging, soft deletes, and scheduled statuses.
- Feature 6: Reactions guidance must match the existing polymorphic reactions table, services, controllers, and counter cache behavior.
- Feature 7: Comments guidance must match the existing comments table, services, controllers, Blade rendering, two-level threading, and comment reaction behavior.
- Feature 8: Follow and friendship guidance must extend the existing `follows`, `pet_followers`, blocks, services, policies, notifications, social follow rate limiter, and Alpine follow button instead of rebuilding the social graph.
