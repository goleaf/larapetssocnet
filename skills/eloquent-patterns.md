# Eloquent Patterns

## Self-referencing many-to-many
Always define both directions for user-to-user relations.

- `followers()` — people following this user
- `following()` — people this user follows
- `blocking()` — people this user has blocked
- `blockedBy()` — people who blocked this user

Use `withPivot()` when pivot has extra columns.
Use `withTimestamps()` when pivot has timestamps.

## Pivot model pattern
When pivot has business logic, create a dedicated pivot model extending `Illuminate\Database\Eloquent\Relations\Pivot`.
Register via `->using(Follow::class)` / `->using(Block::class)`.

## Scope chaining
Define composable scopes.

- `scopeAccepted($q)`
- `scopePending($q)`

## Eager loading rules
- Eager load on index/list pages.
- Never lazy load in loops.
- Use `with()`, `withCount()`, `loadMissing()`, `load()` appropriately.

## Counter cache
- Increment/decrement with model methods.
- Rebuild with `withCount()` + `updateQuietly()`.

## Transactions
Wrap all multi-step writes in `DB::transaction()` and return from the closure.
