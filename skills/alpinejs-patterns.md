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
