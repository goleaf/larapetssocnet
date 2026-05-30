---
name: larapetssocnet-workflow-guides
description: Use when working on repo-specific development workflow, including testing, localization sync, light UI normalization, changelog and commit preparation, or general Laravel service and form-request patterns documented in the project's skills folder.
---

# Larapetssocnet Workflow Guides

Use this skill for repo-specific engineering workflow. Open only the guide files that match the task.

Also activate other matching skills when relevant:
- `using-laravel-superpowers` first for every Laravel implementation, review, debugging, testing, documentation, and maintenance task
- `pest-testing`
- `tailwindcss-development`
- `larapetssocnet-test-hooks-guides` for hook installation, controller-test coverage, and changed-controller guard work

## Workflow Lookup

- Testing and verification: [testing.md](../../../skills/testing.md), [controller-testing.md](../../../controller-testing.md), [controller-testing.md](../../../skills/controller-testing.md), [form-requests.md](../../../skills/form-requests.md), [service-pattern.md](../../../skills/service-pattern.md)
- Hooks and skill routing: [hooks.md](../../../hooks.md), [hooks.md](../../../skills/hooks.md), [skills.md](../../../skills.md), [skill-map.md](../../../skills/skill-map.md)
- Localization and Blade normalization: [localization-light-ui-workflow.md](../../../skills/localization-light-ui-workflow.md), [blade.md](../../../skills/blade.md), [tailwind.md](../../../skills/tailwind.md)
- Git close-out workflow: [git-changelog-workflow.md](../../../skills/git-changelog-workflow.md)
- Core Laravel project rules: [laravel.md](../../../skills/laravel.md), [boost-ai-guidelines.md](../../../skills/boost-ai-guidelines.md), [forms.md](../../../skills/forms.md)

## End-of-Prompt Close-Out

For implementation prompts, use the git close-out workflow before the final response: update affected Markdown, `FEATURES.md`, `CHANGELOG.md`, and git status, then stage only intended files. Commit when git delivery is requested or the task is ready for repository history; push only when explicitly requested.

Use this router to choose the smallest relevant guide file instead of exposing every guide as a separate local skill.
