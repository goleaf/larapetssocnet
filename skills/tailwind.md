# Tailwind

- Use utility-first styling; avoid inline style attributes.
- Keep class sets consistent across similar components.
- Use responsive variants and accessible focus states.
- Use the shared app primitives for repeated UI: `shell-card` / `ui-card`, `ui-panel`, `ui-list-item`, `ui-token`, `ui-media-frame`, and `ui-card-interactive`.
- Avoid introducing new page-local card recipes like `bg-white rounded-xl shadow-sm`; extend the shared SCSS primitives instead.
