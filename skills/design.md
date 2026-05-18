# Design Workflow

Use this guide when changing visual layout, UI primitives, responsive behavior, or design docs.

## Before Editing

- Read root `design.md`.
- Check existing shared components in `resources/views/components/ui`.
- Check tokens and primitives in `resources/scss/abstracts/_tokens.scss` and `resources/scss/components`.
- Use current screenshots or Playwright checks when the change is layout-sensitive.

## Implementation Rules

- Keep Warm Editorial as the only active visual system.
- Do not add dark/light switching, `data-theme`, alternate theme classes, profile theme settings, or theme storage.
- Use `x-ui.page-stack` for in-app operational pages that should align with the shared header width.
- Extend shared primitives before adding page-local card/button/input styles.
- Use responsive constraints and `min-w-0` to prevent overflow.
- Stack action rows on small screens before text or buttons overflow.

## Verification

- Run focused Feature tests for the touched view.
- Run `npm run lint:scss` after SCSS edits.
- Run `npm run build` after asset changes.
- Use Playwright screenshots for desktop and phone viewports on major layout changes.
