---
name: laravel:queues-and-horizon
description: Operate and verify queues with or without Horizon; safe worker flags, failure handling, and test strategies
---

# Queues and Horizon

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.12.0 guidance on PHP 8.4+ with Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4.3, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

Run workers safely, verify execution, and test job behavior.

## Commands

```
# Start worker for this app's class-routed queues
sail artisan queue:work --queue=mail,notifications,comments,default --tries=3 --backoff=5   # or: php artisan queue:work --queue=mail,notifications,comments,default --tries=3 --backoff=5

# Horizon (if installed; this project does not ship Horizon by default)
sail artisan horizon                                               # or: php artisan horizon
sail artisan horizon:terminate                                     # or: php artisan horizon:terminate

# Failed jobs
sail artisan queue:failed                                          # or: php artisan queue:failed
sail artisan queue:retry all                                       # or: php artisan queue:retry all
sail artisan queue:prune-failed --hours=168                       # or: php artisan queue:prune-failed --hours=168
```

## Patterns

- Horizon is not installed in this project by default. Do not publish `config/horizon.php` or document Horizon worker commands as active production steps unless `laravel/horizon` is added to Composer and the app is moved to a Redis-backed queue environment.
- If Horizon is introduced later, publish `config/horizon.php`, import/use `App\Enums\Support\Queue\QueueName`, configure supervisors on the `redis` queue connection, set their `queue` list from `QueueName::workerOrder()`, keep the same `mail,notifications,comments,default` priority order, enable balancing/auto-scaling deliberately, protect the Horizon dashboard through authorization, and use `horizon:terminate` on deploys instead of only `queue:restart`.
- Use named queues for prioritization; this app routes queued mailables to priority 10 `mail`, queued database/user notifications to priority 20 `notifications`, comment fan-out/counter jobs to priority 30 `comments`, and falls back to priority 100 `default` for framework/vendor jobs.
- Add new queue names through `App\Enums\Support\Queue\QueueName`, assign an explicit priority, update worker/monitor docs, and add architecture coverage before routing a new job class.
- Use `App\Support\Queue\HasDefaultQueueRuntime` or an equivalent explicit `failed()` method so every app `ShouldQueue` class logs failed-job context before relying on `failed_jobs` storage.
- Add actionable `Log::warning`/`::error` with context in jobs; avoid dumping serialized payloads or sensitive user fields into logs.
- Idempotency: make jobs safe to retry
- Emit metrics where possible; observe in Horizon or your APM

## Testing Jobs

- Use `Bus::fake()` to assert dispatching in unit tests
- Use integration tests to verify side-effects (DB/IO)
