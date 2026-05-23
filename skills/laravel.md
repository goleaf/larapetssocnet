# Laravel

- Use Laravel 13 conventions with thin controllers and service-layer business logic.
- Prefer Form Requests for validation and authorization.
- Keep model methods focused on relationships/scopes and small helpers.
- Use DB transactions for multi-step writes.
- Use policies/gates for all protected actions.
- This project uses the repository root as the shared-hosting public path; keep root `.htaccess` protections aligned with that layout.
- Shared-hosting deploys should keep the one-archive FTP flow with server-side cleanup/extract, remote SQLite/runtime preservation, and OPcache reset instead of returning to per-folder FTP creation or mirror uploads.
- Production shared-hosting mail should default to `MAIL_MAILER=phpmail` unless SMTP credentials are verified; do not use Symfony sendmail on hosts that disable `proc_open()`.
- Laravel Debugbar is dev-only. Keep `DEBUGBAR_ENABLED=false` in `.env.example`, enable it locally with `APP_DEBUG=true` and `DEBUGBAR_ENABLED=true`, and never force-enable it or open its storage in production.
