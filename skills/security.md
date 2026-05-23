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
- Keep app browsing routes behind the Bootstrap-defined `auth.verified` middleware group plus `banned`, `active_account`, `two_factor`, and `track_last_seen` unless a route is intentionally public or part of the login, registration, password reset, or email verification exception flow.
- Login is handled by the full-page Livewire `pages.auth.login` component and the `AuthenticateUserAction`; keep credential resolution, password checks, account-state checks, session creation, and audit events in that action instead of duplicating them in UI code.
- Reject banned accounts during login even when the supplied password is correct, redirect valid banned attempts to the restricted notice, and record the blocked attempt in `auth_audit_logs`.
- Deny soft-deleted accounts and restrict pending-deletion, deactivated, and suspended accounts to their recovery or notice screens before full app access.
- Failed credential responses must stay generic and must not reveal whether the email, username, or password was incorrect.
- Rate-limit repeated failed login attempts with the persisted `users.failed_login_attempts` and `users.last_failed_login_at` columns when identity is known; keep audit lookups indexed by IP plus a hashed normalized identifier, not raw email.
- Keep auth-only secrets out of public serialization: hide `pending_email`, two-factor secrets, two-factor recovery code hashes, magic link token hashes, and social provider tokens; use encrypted casts for stored secrets.
- Magic login and password reset request flows must not reveal whether an email exists. Magic login requests must use the login-page inline panel or the shared POST route, queue branded magic-link mailables only for matched users, email only the raw 64-character token, store only its SHA-256 lookup hash, and consume links with an atomic `used_at IS NULL` update before signing the user in.
- Password reset requests must share one action across the login inline panel and `/forgot-password`, rate-limit by normalized email before account lookup, queue branded reset mailables only for matched users, and store a SHA-256 token lookup hash alongside Laravel's broker hash.
- Password reset confirmation must resolve `/reset-password/{token}` through the token hash before rendering, keep the email read-only, invalidate database sessions and remember tokens after a successful reset, queue the password-change security alert, and redirect the freshly signed-in user to the feed.
- Auth mail transport errors must be reported without exposing account existence or crashing user-facing registration, verification resend, password reset, magic-link, password-change alert, or login-security alert flows.
- Password-change emergency links must be signed and single-use; consuming one suspends the account, clears sessions and remember tokens, creates a high-priority moderation report, records an auth audit event with safe metadata, and shows the already-taken state on repeat visits.
- Account security device-session lists must query the database `sessions` table by the authenticated user's ID, select only the session columns needed for display, mark the current session by ID, and never expose sessions belonging to other users.
- Individual device logout may delete only a non-current session row owned by the authenticated user. Logout of all other devices must require the current password, delete every other session for that user, clear the remember token, and record an auth audit event.
- Device-session display and login anomaly detection may parse user agents and derive IP geography locally, but must not call a remote geolocation API during page render or login alert checks.
- Login anomaly alerts compare the current login country against the user's last 90 days of auth audit history. Alert action links must be signed, single-use, and token-hash backed; the secure-account action clears sessions and remember tokens, creates a high-priority moderation report, and records an audit event.
- OAuth provider data belongs in `social_accounts`; merge by provider ID first, then by verified email only.
- Logout must clear the remember token, expire the remember-me cookie, invalidate the session, regenerate the CSRF token, and record a `logout` audit event. Password changes and password resets should invalidate other database-backed sessions.
- Do not expose seed users, shared passwords, or quick-login shortcuts on public auth screens.
- Record security-significant auth events in `auth_audit_logs` through `AuthAuditLogger`.
- Email verification uses the `MustVerifyEmail` user contract, queued branded `VerifyEmailAddressMail` mailables, 60-minute signed URLs, hard 403 responses for tampered signatures or mismatched hashes, and a Livewire pending page with per-user resend throttling.
- Registration must validate DOB, terms acceptance, password strength, and the off-screen honeypot before account creation.
- Registration is a deliberate Livewire exception to the normal controller/Form Request pattern: `/register` is handled by the full-page `pages.auth.register` component, which owns form state, validation, and submission directly.
- Registration honeypot submissions must return the same verification-pending redirect shape without creating users, audit logs, or any other database rows.
- Registration, reset-password, profile password, and settings password updates must use `App\Support\Auth\PasswordPolicy` so server validation and generated HTML `passwordrules` hints remain consistent.
