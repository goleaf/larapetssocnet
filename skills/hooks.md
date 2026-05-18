# Hook Workflow

Use this guide when changing `.githooks`, hook installers, or hook-backed quality gates.

## Rules

- Hooks must run from the repository root.
- Hooks must be POSIX shell compatible.
- Hooks must support `SKIP_PROJECT_HOOKS=1`.
- Keep pre-commit fast enough for local work.
- Put heavier checks in pre-push.
- Avoid changing dependencies just to support hooks.

## Current Hooks

- `.githooks/pre-commit`
- `.githooks/pre-push`
- `scripts/install-git-hooks.sh`
- `scripts/controller-test-map.php`

## Verification

- Run `bash -n .githooks/pre-commit .githooks/pre-push scripts/install-git-hooks.sh`.
- Run `php scripts/controller-test-map.php --all --format=json`.
- Run the hook documentation/unit test when it exists.
