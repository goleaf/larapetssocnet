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
  - `private`: `🔒 Only me`
- Hidden input sync:
  - `<input type="hidden" name="{{ $name }}" :value="selected">`

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

