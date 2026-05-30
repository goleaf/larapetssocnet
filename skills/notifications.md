# Notifications

Notification classes are grouped first by delivery provider, then by domain logic:

- `app/Notifications/Database/{Domain}` for in-app database notifications.
- `app/Notifications/Mail/{Domain}` for mail-only operational notifications.

Most product notifications are Laravel database notifications.

- Notification classes should return `['database']` from `via()`.
- Mail-only exceptions should live under `app/Notifications/Mail/*` and return `['mail']` from `via()`.
- `toDatabase()` should return structured payloads (`type`, actor fields, message, action URL).
- Render unread badge with `auth()->user()->unreadNotifications()->count()`.
- Group notifications by date in views when needed.
- Mark as read via `markAsRead()` or dedicated endpoints.
- Test with `Notification::fake()` and `assertSentTo()`.
- Post reaction notifications run through `ReactionNotificationService`. The service must re-check the persisted reaction row before sending `NewReaction`.
- `ReactionNotificationService` must keep one per-post cache guard. If several reactions arrive in the same short window, send one `ReactionBatchNotification` instead of one notification per reaction.
- Daily reaction summary emails are opt-in (`notification_preferences.daily_reaction_summary`) and may use queued mail because they are digest emails, not in-app notification records.
