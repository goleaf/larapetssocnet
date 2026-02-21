# Accessibility (WCAG 2.1 AA)

## Buttons
- Use clear labels.
- Use `aria-pressed` for toggle buttons.
- Use `aria-busy` during loading.
- Use `aria-disabled` for disabled state.

## Modals
- `role="dialog"`, `aria-modal="true"`, `aria-labelledby`.
- Trap focus while open.
- Restore focus on close.
- Close on `Esc`.

## Notifications
- `role="status"` for non-critical updates.
- `role="alert"` + assertive live region for errors.

## Lists
Use semantic `ul/li` for followers/blocked lists and clear labels on items.

## FEED ACCESSIBILITY
- Feed list: `<ul role="feed" aria-label="Your feed">`.
- Each post: `<li aria-label="Post by {name}">`.
- Time: `<time datetime="{{ ISO8601 }}">{{ diffForHumans }}</time>`.
- Reaction buttons: use meaningful `aria-label` and `aria-pressed`.
- Options dropdown trigger: `aria-haspopup="menu"` and `aria-expanded`.
- Media images: alt text like `{{ author name }}'s photo`.
- Videos: provide `aria-label` and consider captions support.
