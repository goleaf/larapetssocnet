# Masonry Grid

Use CSS-only masonry for Explore photos.
No JS masonry library.

```blade
<div class="columns-2 gap-3 md:columns-3 lg:columns-4">
  @foreach($posts as $post)
    <div class="mb-3 break-inside-avoid">
      <x-explore-photo-card :post="$post" />
    </div>
  @endforeach
</div>
```

## Photo Card
- Natural image aspect ratio.
- Hover overlay with author and counts.
- Click navigates to post page.
- No body text.
- Lazy load images.

## Limitation
CSS columns flow top-to-bottom per column. Acceptable for Explore.
