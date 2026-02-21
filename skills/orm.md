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
- Counter rebuilds: use `Model::withCount()` and update via `$model->update([...])`.
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

Correct pattern:

```php
Post::with([...eager loads...])
    ->withCount([...])
    ->forFeed(auth()->user())
    ->when($type, fn ($q) => $q->byType($type))
    ->latest()
    ->paginate(15);
```

Never join the follows table manually.
Use relationship `pluck` for followed IDs:

```php
->whereIn('user_id',
    $user->acceptedFollowing()
        ->pluck('users.id')
        ->push($user->id)
)
```

## Approved Raw Exception: Explore Trending
`orderByRaw('(likes_count + (comments_count * 2)) DESC, created_at DESC')` is approved only in `Post::scopeTrending()`.

Reason: computed column ordering has no direct Eloquent equivalent.
All other raw expressions should be avoided and refactored to ORM-first patterns.

## Visibility Query Patterns
- `Post::query()->visibleTo($viewer)` is the canonical visibility enforcement pattern.
- Never duplicate visibility `where` clauses outside the scope in user-facing queries.
- Keep visibility logic in ORM scopes and relationships only (no raw SQL).
- Query flow should compose as:
  - `Post::query()->visibleTo($viewer)->...`

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

Aggregate counts can use grouped selects where needed. Avoid per-item query loops.

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

```php
$group->members()->attach($user->id, [
    'role' => 'member',
    'status' => 'accepted',
]);

$group->members()->updateExistingPivot($userId, [...]);
$group->members()->detach($userId);
```

## HEALTH LOG ORM PATTERN

Use standard hasMany patterns from `Pet -> healthLogs()` with typed scopes.
