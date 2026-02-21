# Alpine.js

- Use Alpine for local interaction state and network-driven UI updates.
- Keep each component state minimal and deterministic.

## Optimistic UI updates
- Update local state immediately on user action.
- Preserve previous state and revert on fetch error/non-2xx response.

## Cross-component events
- Dispatch custom window events for shared UI updates:
  `window.dispatchEvent(new CustomEvent('follow-toggled', { detail }))`
- Listen with `@follow-toggled.window="..."`.

## Loading and double-submit prevention
- Bind `:disabled` and `aria-busy` while network request is in progress.
