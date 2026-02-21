# Clipboard Share

Post share is client-side only.

## Source URL

Use canonical post URL from `route('posts.show', $post)`.

## Copy Strategy

1. Try `navigator.clipboard.writeText(url)`.
2. Fallback to `document.execCommand('copy')` in `try/catch`.

## UX

- Button switches to `Copied!` state for 2 seconds.
- Reverts automatically via timer.

## Alpine Pattern

```js
x-data({ copied: false, timer: null })
```

Apply share button in:

- post card
- post show
- profile post cards
