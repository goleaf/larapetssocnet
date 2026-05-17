---
name: larapetssocnet-workflow-guides
description: Use when working on repo-specific development workflow, including testing, localization sync, light UI normalization, changelog and commit preparation, or general Laravel service and form-request patterns documented in the project's skills folder.
---

# Larapetssocnet Workflow Guides

Use this skill for repo-specific engineering workflow. Open only the guide files that match the task.

Also activate other matching skills when relevant:
- `using-laravel-superpowers`
- `pest-testing`
- `tailwindcss-development`

## Workflow Lookup

- Testing and verification: [testing.md](../../../skills/testing.md), [form-requests.md](../../../skills/form-requests.md), [service-pattern.md](../../../skills/service-pattern.md)
- Localization and Blade normalization: [localization-light-ui-workflow.md](../../../skills/localization-light-ui-workflow.md), [blade.md](../../../skills/blade.md), [tailwind.md](../../../skills/tailwind.md)
- Git close-out workflow: [git-changelog-workflow.md](../../../skills/git-changelog-workflow.md)
- Core Laravel project rules: [laravel.md](../../../skills/laravel.md), [forms.md](../../../skills/forms.md)

## End-of-Prompt Close-Out

For implementation prompts, use the git close-out workflow before the final response: update affected Markdown, `FEATURES.md`, `CHANGELOG.md`, and git status, then stage only intended files. Commit when git delivery is requested or the task is ready for repository history; push only when explicitly requested.

Use this router to choose the smallest relevant guide file instead of exposing every guide as a separate local skill.
