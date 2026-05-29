# Visibility Selector

Reusable Blade component: `x-visibility-selector`

## Props
- `$selected = 'public'`
- `$name = 'visibility'`
- `$showWarn = false`
- `$postLikes = 0`
- `$postComments = 0`

## UI
- Radio-style options (not `<select>`):
  - `public`: `🌍 Public`
  - `followers`: `👥 Followers`
  - `friends`: `🤝 Friends`
  - `private`: `🔒 Only me`
- Hidden input sync:
  - `<input type="hidden" name="{{ $name }}" :value="selected">`
- In the Livewire post composer, render visibility as a compact bottom-toolbar dropdown immediately before the submit button. The trigger shows the current icon and short label; the panel contains all four radio-card options with one-line explanations and closes immediately after selection.
- Composer visibility selection is request-local state. Initialize it from the user's stored preference, but never update the stored default from the composer.
- Show `Only you will see this post` below the toolbar selector whenever `private` is selected.

## Accessibility
- `role="radiogroup"` and `role="radio"`
- `aria-checked` and managed `tabindex`
- Arrow-key navigation between options
- `Enter` and `Space` to select

## Downgrade Warning
- Edit context only (`$showWarn = true`)
- If interactions exist and user selects stricter visibility:
  - show inline warning
  - do not block submission
