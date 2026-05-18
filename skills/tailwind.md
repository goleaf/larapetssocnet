# Tailwind

- Use utility-first styling; avoid inline style attributes.
- Keep class sets consistent across similar components.
- Use responsive variants and accessible focus states.
- Use the Open Design Warm Editorial tokens exposed from `resources/scss/abstracts/_tokens.scss`.
- Use the shared app primitives for repeated UI: `shell-card` / `ui-card`, `ui-panel`, `ui-list-item`, `ui-token`, `ui-media-frame`, `ui-card-interactive`, `ui-container`, and `ui-section`.
- Avoid introducing new page-local recipes like `bg-white rounded-xl shadow-sm`, gradients, glass effects, dark-mode toggles, or one-off color palettes; extend the shared SCSS primitives instead.
- Keep navigation in the page flow on mobile unless a design explicitly reserves non-overlapped viewport space for fixed controls.
