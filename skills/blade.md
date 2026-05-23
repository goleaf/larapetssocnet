# Blade

- Keep templates presentational and move logic to controllers/services/view models.
- Build reusable components for repeated interaction patterns.
- Escape untrusted output by default.
- Profile followers/following list modals should use the reusable `profile.follow-list-modal` Livewire component with a `followers` or `following` mode instead of duplicating Blade modal bodies.
- Profile edit UI mounts as a nested Livewire modal from the `/@username` profile page; do not reintroduce an inline settings-route POST form or navigate away from the profile context.
- Keep long profile edit modal forms sectioned and scrollable, and route validation failures to the first invalid field instead of leaving users to search through the form.
- Profile edit Basic Information controls should keep their local Alpine behavior small: counters, auto-growing textarea height, and suggestion dropdown state belong in the modal; username and location validation state comes from Livewire/server services.
