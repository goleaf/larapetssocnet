# Visibility Badge

Reusable Blade component: `x-visibility-badge`

## Display Rules
- Show only to post owner on own profile views.
- Never show to other users.
- Never show in feed or explore.

## Variants
- `followers`: `👥 Followers`
- `private`: `🔒 Only me`
- `public`: no badge

## Tailwind
- Base:
  - `inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full`
- Followers:
  - `bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300`
- Private:
  - `bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400`

