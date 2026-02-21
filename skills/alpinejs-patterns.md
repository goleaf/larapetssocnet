# Alpine.js Patterns

## Optimistic UI
- Update local state immediately.
- Keep previous state for rollback on error.
- Show loading state during request.

## Cross-component events
- Dispatch: `window.dispatchEvent(new CustomEvent('event-name', { detail }))`
- Listen: `@event-name.window="handler($event.detail)"`

## Confirm dialogs
Prefer Alpine modal/panel over `confirm()`.

## Fetch helper
Create reusable Alpine helper/magic for CSRF-safe JSON requests.

## Transitions
Use `x-transition` for show/hide, item removal/addition, and error/success banners.

## FEED INTERACTIONS
- Post options dropdown: support click-outside to close.
- Read-more: `x-data="{ expanded: false }"` with toggle.
- Reaction bar: optimistic UI and revert on error.
- Save toggle: optimistic UI and revert on error.
- Share action: `navigator.clipboard.writeText()` with fallback.
- Fire `window` `CustomEvent`s so other UI pieces can react without reload.
