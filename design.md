# Design System

PetSocial uses one design system: Open Design Warm Editorial. It is a warm, editorial, work-focused interface for repeated social-network workflows, not a collection of page-local themes.

## Source Of Truth

- Tokens: `resources/scss/abstracts/_tokens.scss`
- Shared surfaces and containers: `resources/scss/components/_surfaces.scss`
- Buttons and chips: `resources/scss/components/_actions.scss`
- Forms: `resources/scss/components/_forms.scss`
- Navigation: `resources/scss/components/_navigation.scss`
- Blade primitives: `resources/views/components/ui`
- Agent implementation guide: `skills/design.md`

## Non-Negotiable Rules

- Do not add dark/light switching, runtime theme toggles, alternate palette controls, `data-theme` behavior, profile theme fields, or theme preference storage.
- Keep page layouts fluid inside the shared portal container. Do not center operational pages on narrow `max-w-*` wrappers unless the page is intentionally a reading surface.
- Use shared primitives for repeated blocks: `x-ui.page-stack`, `shell-card`, `ui-card`, `ui-panel`, `ui-list-item`, `ui-token`, `ui-media-frame`, `ui-card-interactive`, `ui-container`, `ui-section`, and `btn-*`.
- Avoid new page-local recipes such as one-off gradients, glass panels, arbitrary shadows, `bg-white rounded-xl shadow-sm` card stacks, or competing border palettes.
- Mobile navigation stays in the page flow. It must not cover forms, feed cards, message composers, or primary action rows.

## Visual Language

- Canvas: warm paper (`--surface-page`) with flat framed panels.
- Type: serif-led display headings, compact body copy, and no negative letter spacing.
- Accent: terracotta primary actions, forest/meta secondary cues.
- Radius: controlled by tokens; cards and panels stay consistent instead of page-specific rounding.
- Elevation: flat by default, hover-only where interaction benefit is clear.

## Responsive Standard

- The app shell uses a shared fluid portal container up to 1440px with responsive gutters.
- Desktop left rail scrolls independently within the viewport.
- Main content must use `min-w-0`, wrapping headers, and mobile-stacked action rows where needed.
- Fixed-format elements such as boards, toolbars, counters, and cards need stable dimensions or responsive constraints.

## Verification

For UI work, run the smallest relevant feature tests, `npm run lint:scss`, `npm run build`, and browser screenshots for desktop and phone viewports when the change affects layout.
