# Security

- Validate all request input with Form Requests.
- Authorize state-changing actions with policies/gates.

## CSRF on fetch calls
- All state-changing `fetch()` calls must include `X-CSRF-TOKEN` and `Accept: application/json`.

## Authorization checks
- Use `$this->authorize(...)` in controllers.
- Do not gate logic with raw `if (! auth())` checks.

## Self-action prevention
- Block invalid self-actions (follow self, message self, react to own content when prohibited).

## Block checks before social actions
- Enforce block relationship checks before follow/message/react/comment actions.
