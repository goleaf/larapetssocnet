# Forms

- Use dedicated Form Request per action.
- Normalize values in `prepareForValidation()`.
- Return user-friendly validation messages.
- Long Livewire modal forms should dispatch or otherwise expose the first invalid field target so Alpine can scroll and focus it after validation.
- Profile edit Basic Information keeps identity changes in the nested Livewire modal: display name is capped at 50 characters, bio at 160 characters, username availability is checked debounced with cooldown messaging, and DOB uses the same day/month/year select pattern as registration.
- Location autocomplete must call a server-side service and persist both the display label and coordinate fields when a suggestion is selected.
- Profile edit Social Links should keep website, Facebook, and YouTube as URL fields with blur validation, while Twitter/X and Instagram are edited as `@username` handles and normalized to canonical public profile URLs before storage.
