# Visibility Enforcement

Use all three layers together.

## Layer 1: Query Scope (primary)
- Always use `Post::query()->visibleTo($viewer)`.
- Never hand-write visibility `where` clauses outside the scope for user-facing queries.
- Scope must enforce:
  - `public` / `followers` / `private`
  - account privacy ceiling
  - block relationships
  - banned-account filtering
- Accept nullable viewer: `?User`.

## Layer 2: Policy (single-item access)
- `PostPolicy::view(?User $viewer, Post $post): bool`.
- Route-model-bound show endpoints must call `$this->authorize('view', $post)`.
- This guards direct URL access.

## Layer 3: View Safety
- Never render restricted content unless query and policy already allowed it.
- Show visibility badges only to owner on own profile.
- Keep selector visible in create/edit forms.
- On edit, warn (do not block) if visibility downgrade happens on engaged posts.

