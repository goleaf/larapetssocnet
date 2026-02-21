# Git Changelog Workflow

Use this at the end of every implementation task.

## Required end-of-work steps
1. Check git status and changed files.
2. Update changelog with user-facing summary of what changed.
3. Prepare a clear commit message (Conventional Commits format).
4. Stage relevant files with `git add`.
5. Commit with the prepared message.

## Changelog rules
- Keep entries short and user-facing.
- Group by `Added`, `Changed`, `Fixed`, `Removed` when possible.
- Include impacted areas (API, UI, DB, tests).

## Commit message rules
- Use Conventional Commits (`feat:`, `fix:`, `refactor:`, `test:`, `chore:`).
- Keep subject specific and scoped.
- Mention major modules/features touched.

## Example
- Commit: `feat(posts): add post service pipeline with media uploads and observer side effects`
- Changelog sections: `Added`, `Changed`, `Tests`.
