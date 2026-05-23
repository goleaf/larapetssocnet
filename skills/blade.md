# Blade

- Keep templates presentational and move logic to controllers/services/view models.
- Build reusable components for repeated interaction patterns.
- Escape untrusted output by default.
- Profile edit UI mounts as a nested Livewire modal from the `/@username` profile page; do not reintroduce an inline settings-route POST form or navigate away from the profile context.
- Keep long profile edit modal forms sectioned and scrollable, and route validation failures to the first invalid field instead of leaving users to search through the form.
