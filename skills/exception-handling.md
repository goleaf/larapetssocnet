# Exception Handling

## Pattern
Domain exceptions live in `app/Exceptions` and carry meaningful messages/status.

Suggested social exceptions:
- `CannotBlockSelfException` (422)
- `CannotBlockAdminException` (403)
- `AlreadyBlockedException` (422)
- `NotBlockingException` (422)
- `UserBlockedException` (403)
- `FollowRequestCooldownException` (429)

Map to JSON for API requests and validation/error bags for web requests.
