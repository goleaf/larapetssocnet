# Forms

- Use dedicated Form Request per action.
- Normalize values in `prepareForValidation()`.
- Return user-friendly validation messages.
- Long Livewire modal forms should dispatch or otherwise expose the first invalid field target so Alpine can scroll and focus it after validation.
