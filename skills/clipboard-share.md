# Clipboard Share

Post share is exposed through the shared Livewire `posts.share-menu`.

## Source URL

Use canonical post URL from `route('posts.show', $post)`.

## Share Menu

Render these options in order:

1. Repost.
2. Quote post.
3. Copy link.

Use a mobile bottom sheet and a desktop popover. In post cards, prefer Alpine `x-teleport="body"` for the overlay so the menu is not clipped by the card container.

## Copy Strategy

1. Try `navigator.clipboard.writeText(url)`.
2. Fallback to `document.execCommand('copy')` in `try/catch`.
3. Call the server-side share tracking action after a successful browser copy.

## UX

- Copy link shows a `Link copied!` tooltip for 2 seconds.
- Reverts automatically via timer.

## Alpine Pattern

```js
x-data="postShareMenu({ url })"
```

Apply share button in:

- post card
- post show
- profile post cards
