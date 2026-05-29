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
- Multi-step actions that are called from controllers, Livewire, jobs, and other services should accept DTOs instead of raw UI arrays. For post creation, build `PostCreationInput` before calling `CreatePostAction` and keep the action independent of HTTP and Livewire concerns.

## Example
`BlockService`:
- `block(User $actor, User $target): void`
- `unblock(User $actor, User $target): void`
- `getBlockedUsers(User $user): LengthAwarePaginator`
- `isBlocked(User $actor, User $target): bool`
- `canInteract(User $actor, User $target): bool`

## Observer vs Service
- Services own core business logic, guards, transactions, and return values.
- Observers own side effects (counter updates, logs, badge checks, hashtag sync).
- Do not move core domain decisions into observers.
