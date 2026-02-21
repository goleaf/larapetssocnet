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
