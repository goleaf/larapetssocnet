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

- Feature 1: Authentication and registration guidance must match the existing Breeze/controller/Form Request flow unless explicitly refactored. Current implementation includes normalized email/username login, banned-user login rejection to the restricted notice, soft-deleted account denial, password-confirmed pending-deletion cancellation, password-confirmed deactivation recovery, suspended-account restricted access, verified-email gating for app pages, safe intended redirects, rate-limited failed login attempts, safe logout session invalidation, DOB/terms/honeypot registration checks, password-strength validation, and auth audit logging.
- Feature 2: User profile guidance must match the viewer-aware public profile controller, Blade tabs, follow/request, privacy, username redirect, search, gallery, audit, and media flows. Profiles hide unavailable owners, block restricted viewers, gate tabs/counts/location/messages by section permission, eagerly load owner media/pet card context, compute owner completeness from narrow summary queries, use indexed daily profile-view upserts, keep mutual follower previews as SQL intersections, render the verified profile badge only from `users.is_verified`, keep the hero identity panel privacy-aware, handle empty, guest, high-volume, followers-only, and blocked states intentionally, never search by private email, and protect direct gallery URLs with profile visibility checks.
- Feature 3: Pet profile guidance must match the existing pet controller, pet followers, media, gallery, health, adoption, and visibility flows.
- Feature 4: News feed guidance must match the existing feed controller, cursor pagination, follow graph, pet follow graph, Blade post cards, one Warm Editorial feed surface, and implemented `source=people|pets` plus `type=text|photo|video` filters.
- Shared UI guidance uses the Open Design Warm Editorial system for repeated blocks: `shell-card` / `ui-card`, `ui-panel`, `ui-list-item`, `ui-token`, `ui-media-frame`, `ui-card-interactive`, `ui-container`, `ui-section`, and the shared button variants.
- Shared form guidance keeps select controls on the shared `form-select` + custom chevron pattern without allowing Tailwind Forms to add a second background arrow.
- Shared design guidance forbids dark/light theme switching, runtime visual style switchers, `data-theme` controls, user-facing profile theme fields, and theme preference storage.
- Application shell guidance keeps the desktop left rail scroll-contained to the viewport without giving individual sidebar widgets capped internal scroll areas.
- Application shell guidance keeps mobile quick navigation in the normal page flow so it never covers forms, feed cards, or action rows, and keeps the portal container fluid up to the shared 1440px maximum.
- Application shell guidance relies on Livewire automatic asset injection instead of manual `@livewireStyles` / `@livewireScripts` directives in the shared layout.
- Messages guidance keeps inbox and conversation content on one full-width responsive main-column surface with wrapping headers and mobile-safe form actions.
- Local debugging guidance uses Laravel Debugbar as a dev-only tool; keep committed env defaults disabled and enable it only in local debug sessions.
- Project memory guidance is exposed through the local `larapetssocnet-memory-guides` skill and `skills/memory.md` for durable preferences, prior rollout lookup, and explicit memory updates.
- Project design guidance is exposed through root `design.md`, `skills/design.md`, and the local `larapetssocnet-design-guides` router so future UI work keeps one Warm Editorial standard.
- Project skill guidance requires `using-laravel-superpowers` first for every Laravel task, followed by every matching project/router skill for the touched domains. It is documented through root `skills.md`, `skills/skill-map.md`, compact local routers, and the project-installed Laravel 13-aligned Superpowers Laravel pack under `.claude/skills` instead of one exposed Codex skill per detailed guide.
- Project hook guidance is exposed through root `hooks.md`, `controller-testing.md`, `skills/hooks.md`, `skills/controller-testing.md`, and the local `larapetssocnet-test-hooks-guides` router.
- Local git hooks can be installed with `bash scripts/install-git-hooks.sh`; pre-commit runs Composer validation, Pint, and changed-controller test mapping, while pre-push runs changed-controller mapping, feature/unit tests, SCSS lint, and Vite build.
- Pet profile guidance keeps profile summary, tabs, and tab content on the shared full-width `x-ui.page-stack` so main blocks align with the app page header instead of using page-local max-width wrappers.
- Feature 5: Posts guidance must match the existing post controllers, actions, validation, media limits, visibility, tagging, soft deletes, and scheduled statuses.
- Feature 6: Reactions guidance must match the existing polymorphic reactions table, services, controllers, and counter cache behavior.
- Feature 7: Comments guidance must match the existing comments table, services, controllers, Blade rendering, two-level threading, and comment reaction behavior.
- Feature 8: Follow and friendship guidance must extend the existing `follows`, `pet_followers`, blocks, services, policies, notifications, social follow rate limiter, and Alpine follow button instead of rebuilding the social graph.
- Feature 9: Groups guidance must extend the existing controller/service/policy groups module, `group_members`, `group_posts`, `posts.group_id`, three-level privacy model, role hierarchy, and the implemented archive/read-only lifecycle instead of replacing it with a separate subsystem.
