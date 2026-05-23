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
- Profile edit save success uses browser events for local UI work: add toast messages through the shared Alpine toast store and update changed-username profile URLs with `history.replaceState` without reloading.

## Loading and double-submit prevention
- Bind `:disabled` and `aria-busy` while network request is in progress.
