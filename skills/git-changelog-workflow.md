# Git Changelog Workflow

Use this at the end of every implementation task.

## Required end-of-work steps
1. Review the completed task and identify every affected Markdown file.
2. Update root docs, relevant `skills/*.md` guides, and `FEATURES.md` when feature scope, behavior, commands, architecture, or workflow changes.
3. Update `CHANGELOG.md` with a user-facing summary of what changed.
4. Check `git status --short` and changed files.
5. Prepare a clear commit message in Conventional Commits format.
6. Stage only relevant files with `git add`, preserving unrelated dirty-tree changes.
7. Commit with the prepared message when git delivery is requested or the work is ready for repository history.
8. Push only when explicitly requested.

## Changelog rules
- Keep entries short and user-facing.
- Group by `Added`, `Changed`, `Fixed`, `Removed` when possible.
- Include impacted areas (API, UI, DB, tests).

## Markdown rules
- Keep Markdown updates scoped to files affected by the prompt.
- Update `FEATURES.md` for feature status, scope, implementation notes, or prompt-guide changes.
- Keep AI guidance files consistent with the current stack and repo workflow.
- Avoid broad formatting churn in unrelated docs.

## Commit message rules
- Use Conventional Commits (`feat:`, `fix:`, `refactor:`, `test:`, `chore:`).
- Keep subject specific and scoped.
- Mention major modules/features touched.

## Example
- Commit: `feat(posts): add post service pipeline with media uploads and observer side effects`
- Changelog sections: `Added`, `Changed`, `Tests`.
