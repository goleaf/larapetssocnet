# Memory

Use this guide for project memory in `/Users/andrejprus/Herd/larapetssocnet`.

## Read Workflow

- Start with `/Users/andrejprus/.codex/memories/MEMORY.md` for repo-specific keywords such as `larapetssocnet`, `SCSS`, `Tailwind`, `Warm Editorial`, `Debugbar`, `theme switching`, `authenticated pages`, and recent feature names.
- Open rollout summaries only when `MEMORY.md` points to a directly relevant one or when exact command, error, or verification evidence is needed.
- Prefer current repository files over stale memory when behavior may have changed.
- If memory is used in a final answer, include the required `<oai-mem-citation>` block from the active instructions.

## External Skill Lookup

- The official Mem0 Codex plugin provides MCP-backed persistent memory, but it requires a `MEM0_API_KEY` plus Codex MCP or plugin configuration outside this repository.
- Keep this project skill focused on the current file-backed memory workflow unless the user explicitly asks to configure Mem0 globally.
- Upstream reference: `https://github.com/mem0ai/mem0/tree/main/mem0-plugin`.

## Write Workflow

- Write memory only when the user explicitly asks to remember, save, or keep a rule for future work.
- Add one small note under `/Users/andrejprus/.codex/memories/extensions/ad_hoc/notes/`.
- Do not edit generated memory files directly.
- Keep notes specific, small, and actionable.

## Durable Project Rules

- Do not reintroduce dark/light theme switching, runtime application visual style switchers, or `data-theme` controls. Profile theming is allowed only through the five accessible enum/config choices on the public profile root.
- Application browsing pages stay private by default unless product policy explicitly changes.
- Tailwind v4 must run after Sass through PostCSS.
- Shared-hosting root web-surface rules stay active: keep the root front controller and root `.htaccess` protections aligned.
