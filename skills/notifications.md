# Notifications

Laravel database-notifications only.

- Notification classes should return `['database']` from `via()`.
- `toDatabase()` should return structured payloads (`type`, actor fields, message, action URL).
- Render unread badge with `auth()->user()->unreadNotifications()->count()`.
- Group notifications by date in views when needed.
- Mark as read via `markAsRead()` or dedicated endpoints.
- Test with `Notification::fake()` and `assertSentTo()`.
