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

- Feature 1: Authentication and registration guidance must match the existing Breeze/controller/Form Request flow unless explicitly refactored. Current implementation includes email/username login, verified-email gating for app pages, DOB/terms/honeypot registration checks, password-strength validation, and auth audit logging.
- Feature 2: User profile guidance must match the existing public profile controller, Blade tabs, follow/request, privacy, and media flows.
- Feature 3: Pet profile guidance must match the existing pet controller, pet followers, media, gallery, health, adoption, and visibility flows.
- Feature 4: News feed guidance must match the existing feed controller, cursor pagination, follow graph, pet follow graph, Blade post cards, one Warm Editorial feed surface, and implemented `source=people|pets` plus `type=text|photo|video` filters.
- Shared UI guidance uses the Open Design Warm Editorial system for repeated blocks: `shell-card` / `ui-card`, `ui-panel`, `ui-list-item`, `ui-token`, `ui-media-frame`, `ui-card-interactive`, `ui-container`, `ui-section`, and the shared button variants.
- Application shell guidance keeps the desktop left rail scroll-contained to the viewport without giving individual sidebar widgets capped internal scroll areas.
- Application shell guidance keeps mobile quick navigation in the normal page flow so it never covers forms, feed cards, or action rows.
- Application shell guidance relies on Livewire automatic asset injection instead of manual `@livewireStyles` / `@livewireScripts` directives in the shared layout.
- Feature 5: Posts guidance must match the existing post controllers, actions, validation, media limits, visibility, tagging, soft deletes, and scheduled statuses.
- Feature 6: Reactions guidance must match the existing polymorphic reactions table, services, controllers, and counter cache behavior.
- Feature 7: Comments guidance must match the existing comments table, services, controllers, Blade rendering, two-level threading, and comment reaction behavior.
- Feature 8: Follow and friendship guidance must extend the existing `follows`, `pet_followers`, blocks, services, policies, notifications, social follow rate limiter, and Alpine follow button instead of rebuilding the social graph.
- Feature 9: Groups guidance must extend the existing controller/service/policy groups module, `group_members`, `group_posts`, `posts.group_id`, three-level privacy model, role hierarchy, and the implemented archive/read-only lifecycle instead of replacing it with a separate subsystem.
