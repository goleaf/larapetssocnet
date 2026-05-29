# Forms

- Use dedicated Form Request per action.
- Exception: registration is intentionally handled by the full-page Livewire `pages.auth.register` component instead of a controller/Form Request; keep all registration field state and validation in that component unless the product decision is reversed.
- Exception: login is intentionally handled by the full-page Livewire `pages.auth.login` component backed by `AuthenticateUserAction`; keep the single credential field, lockout countdown, remember-me checkbox, and inline reset panel colocated with that page.
- Exception: password reset request and confirmation are intentionally handled by full-page Livewire components (`pages.auth.forgot-password`, `pages.auth.reset-password`) backed by shared auth actions; keep reset request status copy non-enumerating, reset links signed, reset password strength/match feedback aligned with registration, and successful resets redirected to login after session invalidation.
- Email verification pending is intentionally handled by the full-page Livewire `pages.auth.verify-email` component so resend state, success/error/toast feedback, and Alpine countdown behavior stay colocated with the page.
- Auth forms that trigger mail delivery should keep user-facing success or non-enumerating copy separate from transport success; mailer exceptions belong in reporting/logs, not in registration, verification resend, reset, or magic-link form errors.
- Onboarding is intentionally handled by the full-page Livewire `pages.onboarding` component. Keep all three steps' state as public properties, preserve entered data when navigating back, save optional profile details only on Continue, skip without saving step data, create pets through `CreatePetAction`, and persist follow toggles immediately instead of buffering them until final completion.
- Normalize values in `prepareForValidation()`.
- Return user-friendly validation messages.
- New password fields should use `x-ui.input` with `autocomplete="new-password"` so the shared `PasswordPolicy` and Laravel-generated `passwordrules` attribute stay aligned with server validation.
- Long Livewire modal forms should dispatch or otherwise expose the first invalid field target so Alpine can scroll and focus it after validation.
- Profile edit Basic Information keeps identity changes in the nested Livewire modal: display name is capped at 50 characters, bio at 160 characters, username availability is checked debounced with cooldown messaging, and DOB uses the same day/month/year select pattern as registration.
- Profile edit modal saves must validate through `UpdateProfileModalRequest` before calling `UpdateProfileAction`; keep blur-only Livewire validation proxied to the same request rules where possible.
- Location autocomplete must call a server-side service and persist both the display label and coordinate fields when a suggestion is selected.
- Profile edit Social Links should keep website, Facebook, and YouTube as URL fields with blur validation, while Twitter/X and Instagram are edited as `@username` handles and normalized to canonical public profile URLs before storage.
- Profile edit Privacy exposes only high-impact toggles in the modal. Save Account Visibility, public age display, and email discovery through dedicated authorized Livewire actions immediately, not through the main profile form submit.
