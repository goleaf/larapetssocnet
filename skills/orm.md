# ORM Skill

The golden rule: zero raw SQL queries anywhere in the app.
All database interactions must go through Eloquent ORM or the Query Builder using model relationships.

## Rules
- Never use `DB::statement()` with raw SQL strings.
- Never use `DB::select()` with raw strings.
- Never write `whereRaw()` unless there is absolutely no Eloquent equivalent, and flag it for review.
- Never use `DB::table()` directly when a Model exists.
- Always use `Model::query()` as the starting point.
- Always use relationship methods (`$user->followers()`, `$user->blocking()`, etc.) instead of manual joins.
- Counter increments/decrements: use `$model->increment('col')` and `$model->decrement('col')`.
- Counter rebuilds: use `withCount()` and `updateQuietly()`.
- Bulk updates: use `Model::whereIn()->update([])`.
- Existence checks: use relationship/query `->exists()`.
- Pivots: use `sync()`, `syncWithoutDetaching()`, `attach()`, `detach()`, `updateExistingPivot()`.
- Upserts: use `Model::upsert()`.
- Never access pivot data via `DB::table()`.

## Forbidden patterns
- `DB::table('follows')->where(...)->exists()`
- `DB::statement("UPDATE users SET...")`
- `DB::select("SELECT * FROM...")`
- `$user->whereRaw('id IN (SELECT...)')`

## Correct patterns
- `$user->following()->wherePivot('status', 'accepted')->exists()`
- `$user->increment('followers_count')`
- `User::whereIn('id', $ids)->update(['following_count' => 0])`
- `$user->blocking()->sync([])`
- `UserFollow::where('follower_id', $id)->where(...)->first()`

## MediaLibrary ORM patterns
- Use `$model->addMedia($file)->toMediaCollection('collection')`.
- Use `$model->getFirstMediaUrl('photos', 'medium')` and `$model->getMedia('photos')`.
- Use `$model->hasMedia('photos')` for checks.
- Never query the `media` table manually.

## FEED QUERY ORM PATTERN
The feed query must use only ORM relationships.

```php
Post::query()
    ->forFeed($viewerId)
    ->with(['user', 'author', 'author.media', 'pet', 'media', 'tags'])
    ->withCount(['likes', 'comments'])
    ->withExists([
        'likes as liked_by_viewer' => fn ($q) => $q->where('likes.user_id', $viewerId),
    ])
    ->orderByDesc('posts.created_at')
    ->orderByDesc('posts.id')
    ->cursorPaginate(15);
```

Never join the follows table manually in controllers.

## Approved Raw Usage
Raw expressions are only permitted inside model internals where no Eloquent alternative exists.
Example: `HasReactions::reactionCounts()` uses a grouped `DB::raw('COUNT(*)')` inside the model trait.
Do not use raw ordering or selects in controllers or services.

## Visibility Query Patterns
- `Post::query()->visibleTo($viewer)` is the canonical visibility enforcement pattern.
- Never duplicate visibility `where` clauses outside the scope in user-facing queries.
- Keep visibility logic in ORM scopes and relationships only (no raw SQL).

## SAVED POSTS ORM PATTERN

Use relationship methods only:

```php
$user->savedPosts()->attach($post->id);
$user->savedPosts()->detach($post->id);
$user->savedPosts()->where('posts.id', $postId)->exists();
```

## POLYMORPHIC REACTION ORM PATTERN

```php
$post->reactions();
$comment->reactions();
$user->reactions();
```

Aggregate counts should come from grouped queries or preloaded collections. Avoid per-item query loops.

## REPORTING ORM PATTERN

Use idempotent creation:

```php
Report::firstOrCreate([...], [...]);
```

## COMMENT THREADING ORM PATTERN

- top-level: `whereNull('parent_id')`
- replies: `where('parent_id', $id)`
- never allow depth > 1 reply level

## PET FOLLOW ORM PATTERN

```php
$user->petFollowing()->attach($pet->id);
$user->petFollowing()->detach($pet->id);
$user->petFollowing()->where('pet_id', $id)->exists();
```

## GROUP MEMBERSHIP ORM PATTERN

Group membership is modeled by `GroupMember` (hasMany), not a belongsToMany pivot.

```php
$group->members()->create([
    'user_id' => $user->id,
    'role' => 'member',
    'status' => 'active',
]);

$group->members()->where('user_id', $userId)->delete();
```

## HEALTH LOG ORM PATTERN

Use standard hasMany patterns from `Pet -> healthLogs()` with typed scopes.
