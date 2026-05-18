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

## Authentication security
- Keep app browsing routes behind `auth`, `verified`, `banned`, and `track_last_seen` unless a route is intentionally public.
- Reject banned accounts during login even when the supplied password is correct, keep the public error generic, and record the blocked attempt in `auth_audit_logs`.
- Rate-limit repeated failed login attempts by normalized email/username plus IP.
- Logout must invalidate the session, regenerate the CSRF token, and record a `logout` audit event.
- Do not expose seed users, shared passwords, or quick-login shortcuts on public auth screens.
- Record security-significant auth events in `auth_audit_logs` through `AuthAuditLogger`.
- Registration must validate DOB, terms acceptance, password strength, and the off-screen honeypot before account creation.
