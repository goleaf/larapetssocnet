# Pivot Models

## When to use
Use pivot models when pivot rows carry domain logic/state transitions.

## Setup
- Create: `php artisan make:model Follow --pivot`
- Register: `->using(Follow::class)`

## Useful features
- Add methods on pivot model: `approve()`, `reject()`, `cancel()`.
- Cast pivot attributes via `$casts`.
- Access pivot: `$user->followers->first()?->pivot->status`.
- Update pivot: `->updateExistingPivot($targetId, ['status' => 'accepted'])`.

## When not to use
For simple boolean-like pivots without behavior, plain relationships with `withPivot()` are enough.
