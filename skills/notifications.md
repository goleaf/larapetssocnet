# Notifications

Laravel database-notifications only.

- Notification classes should return `['database']` from `via()`.
- `toDatabase()` should return structured payloads (`type`, actor fields, message, action URL).
- Render unread badge with `auth()->user()->unreadNotifications()->count()`.
- Group notifications by date in views when needed.
- Mark as read via `markAsRead()` or dedicated endpoints.
- Test with `Notification::fake()` and `assertSentTo()`.
- Post reaction notifications are delayed by `SendReactionNotificationJob` for the four-second undo window. The job must re-check the persisted reaction row before sending `NewReaction`.
- Daily reaction summary emails are opt-in (`notification_preferences.daily_reaction_summary`) and may use queued mail because they are digest emails, not in-app notification records.
