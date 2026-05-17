# Laravel

- Use Laravel 13 conventions with thin controllers and service-layer business logic.
- Prefer Form Requests for validation and authorization.
- Keep model methods focused on relationships/scopes and small helpers.
- Use DB transactions for multi-step writes.
- Use policies/gates for all protected actions.
- This project uses the repository root as the shared-hosting public path; keep root `.htaccess` protections aligned with that layout.
