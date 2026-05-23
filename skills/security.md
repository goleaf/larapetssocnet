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
- Enforce the mutual-follow messaging rule in authorization and service/action layers, not only by hiding profile buttons or menu links.
- Profile Block actions must submit to the server and reuse `BlockService` so follow cleanup, block creation, redirects, and blocked-content visibility enforcement happen atomically instead of through view-only state.

## Profile security
- Treat every profile section as private until `ProfileVisibilityService` or the matching visibility service proves the viewer can see it.
- Reject unavailable profile owners and restricted viewers before loading profile posts, pets, photos, followers, following, likes, groups, message actions, or direct gallery URLs.
- Never search public profiles by email address or expose private contact/security fields through profile search, stats, tabs, locked states, or sidebar previews.
- Username redirects must not resolve reserved names and must not point to banned, suspended, deactivated, deleted, or pending-deletion users.
- Profile audit events should record changed field names and safe metadata only; never log raw bio contents, private contact values, media secrets, or security state.
- Profile view analytics counts are private owner-only data; never pass or render them for guests or profile visitors.
- Username changes from the profile edit modal must go through `UpdateProfileAction` and `UsernameService` so reserved names, uniqueness, redirects, and the 30-day cooldown are enforced server-side, not only through Livewire availability UI.
- Profile edit privacy toggles must re-authorize the owner on every Livewire action and may store the email-discovery preference, but must not expose raw email values or add public email search without a dedicated visibility policy.

## Authentication security
- Keep app browsing routes behind `auth`, `banned`, `active_account`, `verified`, and `track_last_seen` unless a route is intentionally public.
- Reject banned accounts during login even when the supplied password is correct, redirect valid banned attempts to the restricted notice, and record the blocked attempt in `auth_audit_logs`.
- Deny soft-deleted accounts and restrict pending-deletion, deactivated, and suspended accounts to their recovery or notice screens before full app access.
- Rate-limit repeated failed login attempts by normalized email/username plus IP.
- Persist failed-login counters on the user when identity is known and keep audit lookups indexed by IP plus a hashed normalized identifier, not raw email.
- Keep auth-only secrets out of public serialization: hide `pending_email`, two-factor secrets, two-factor recovery codes, and social provider tokens; use encrypted casts for stored secrets.
- Logout must invalidate the session, regenerate the CSRF token, and record a `logout` audit event.
- Do not expose seed users, shared passwords, or quick-login shortcuts on public auth screens.
- Record security-significant auth events in `auth_audit_logs` through `AuthAuditLogger`.
- Registration must validate DOB, terms acceptance, password strength, and the off-screen honeypot before account creation.
- Registration, reset-password, profile password, and settings password updates must use `App\Support\Auth\PasswordPolicy` so server validation and generated HTML `passwordrules` hints remain consistent.
