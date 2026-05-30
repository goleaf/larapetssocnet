# Eager Loading Patterns

Definitive guide to eager loading for feed pages.

## List Query Contract
Every list query must explicitly define:

- parent selected columns,
- eager-loaded relations and their selected columns,
- pagination strategy,
- deterministic sort order,
- aggregate counts or existence flags required by the UI,
- viewer-specific state batched outside item loops.

Keep `Model::preventLazyLoading(! app()->isProduction())` enabled so violations fail in local and test environments. Do not rely on Laravel automatic eager loading for critical feed, profile, search, or modal surfaces because it is beta.

## Feed Eager Load Set
Always use this set unless there is measured justification to change it:

```php
Post::with([
    'author',
    'author.media',
    'pet',
    'pet.media',
    'media',
    'hashtags',
    'reactions',
])->withCount([
    'comments',
    'reactions',
]);
```

- `author`: name, username, avatar, `is_verified`, `is_private`.
- `author.media`: avatar image.
- `pet`: name, slug, species (nullable).
- `pet.media`: pet profile photo (nullable).
- `media`: all post photos/videos.
- `hashtags`: name and slug.
- `reactions`: type and user_id for reaction state.

Do not add extra eager loads without a measured reason.
When narrowing columns, always keep the parent key, relation key, and foreign key columns required by Eloquent hydration.

## Passing Data To Components
- Pass eager-loaded `$post` into `x-post-card`.
- Do not call extra counting queries like `$post->reactions()->count()` in loops.
- Use `withCount` values such as `$post->reactions_count` and `$post->comments_count`.

## Auth User Reaction State
Load reactions for all posts on page in one query:

```php
$myReactions = auth()->user()->reactions()
    ->whereIn('reactable_id', $postIds)
    ->where('reactable_type', Post::class)
    ->get()
    ->keyBy('reactable_id');
```

Blade usage:

```blade
{{ $myReactions->get($post->id)?->type }}
```

## Auth User Saved State
Load saved state in one query:

```php
$mySaved = auth()->user()->savedPosts()
    ->whereIn('posts.id', $postIds)
    ->pluck('posts.id')
    ->flip();
```

Blade usage:

```blade
{{ $mySaved->has($post->id) }}
```

## Explore Eager Load Set
Explore uses the feed core eager loads and always has an authenticated viewer.

For authenticated users:
- Use the same post eager load set.
- Load `myReactions` in one query.
- Load `mySaved` in one query.
