# Service Pattern

## Location and naming
- `app/Services/`
- `{Feature}Service.php`

## Rules
- Constructor injects dependencies only.
- One responsibility per method.
- Multi-step side effects go inside `DB::transaction()`.
- Methods return typed values or throw exceptions.
- Never return views/responses from services.
- Never accept `Request` in services; use typed params.

## Example
`BlockService`:
- `block(User $actor, User $target): void`
- `unblock(User $actor, User $target): void`
- `getBlockedUsers(User $user): LengthAwarePaginator`
- `isBlocked(User $actor, User $target): bool`
- `canInteract(User $actor, User $target): bool`
