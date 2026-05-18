# Git Hooks

The project includes local git hooks under `.githooks/` plus an installer script.

## Install

```bash
bash scripts/install-git-hooks.sh
```

This sets:

```bash
git config core.hooksPath .githooks
```

## Hooks

- `pre-commit`: validates Composer metadata, formats dirty PHP with Pint, and checks changed controllers against the controller-test map.
- `pre-push`: runs the changed-controller map, Feature tests, Unit tests, SCSS lint, and the Vite build.

Emergency bypass:

```bash
SKIP_PROJECT_HOOKS=1 git commit -m "..."
SKIP_PROJECT_HOOKS=1 git push
```

Use the bypass only for local emergency work. The skipped checks still need to pass before final delivery.

## Controller Coverage Audit

```bash
php scripts/controller-test-map.php --all
php scripts/controller-test-map.php --changed
php scripts/controller-test-map.php --strict
```

The changed mode is hook-friendly and fails when changed controllers have no discoverable related tests. Strict mode is an audit target for improving existing controller coverage over time.
