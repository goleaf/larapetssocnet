# Laravel Octane Readiness Audit

Date: 2026-05-30

## Verdict

Not recommended yet for production Octane.

The application is already on a current stack (Laravel 13.12.0, Livewire 4.3.0, PHP 8.5.0 locally and compatible with Laravel 13's PHP 8.3+ floor), but Octane should not be enabled until the blockers below are resolved and a production server target is chosen. For the current local/shared-hosting style environment, Octane's operational complexity is likely not worth it unless the app moves to a high-traffic, API-heavy, dashboard-heavy, SaaS, or Livewire-heavy deployment where lower boot cost and higher request throughput justify long-running worker operations.

## Current stack and runtime findings

- Laravel: 13.12.0.
- PHP: 8.5.0 locally via Laravel Herd; project target remains PHP 8.3+ with current locked dependencies requiring PHP 8.4+.
- Livewire: 4.3.0.
- Local server environment: Laravel Herd 1.28.0 on macOS.
- Current web server: Herd-managed Nginx/PHP-FPM style local site at `https://larapetssocnet.test`.
- Database driver: SQLite locally.
- Cache driver: `file` in `.env`; config default is `database`.
- Session driver: `database`.
- Queue driver: `database`.
- Broadcast driver: `log`; no Reverb package installed.
- Redis PHP extension: available.
- Redis server/CLI: not available on PATH during the audit.
- FrankenPHP, RoadRunner, and `rr`: not available on PATH during the audit.
- Swoole/Open Swoole PHP extensions: not loaded during the audit.
- Octane package: not installed.
- Horizon, Telescope, Pulse, Reverb packages: not installed.
- Scheduler: active commands for profile wrapped generation, scheduled posts, pet birthday reminders, pet owner invitation/transfer expiry, health reminders, daily reaction summaries, and comment quality scoring.
- Queues: queued mail/notifications exist, and queued comment side effects now live under `app/Actions/Comments` with scalar IDs instead of the forbidden `app/Jobs` application folder.

## Long-running-worker risks found

### Blockers before enabling Octane

1. Server runtime is absent. No Octane package and no FrankenPHP/RoadRunner/Swoole/Open Swoole runtime is currently installed.
2. The local `.env` uses the file cache store. Production Octane should use Redis, Memcached, database, or another production-safe shared store; prefer Redis for cache, sessions, queues, locks, and rate limiter state when available.
3. Redis extension is loaded but no Redis service was available on PATH. Confirm the production Redis service and `REDIS_*` settings before Octane.
4. The deployment surface is currently a shared-hosting repository root/Herd local site. Octane requires a process supervisor and reverse proxy configuration, plus worker reloads after every release.
5. Livewire comment components still retain a full `Comment` Eloquent model in public component state (`CommentCard::$comment`). This is not an Octane leak by itself because Livewire request state is per request, but it increases serialized payload and hydration cost. Prefer storing IDs/compact arrays and rehydrating via computed/query methods.
6. Several schema/route static caches exist. They appear to cache process-wide schema or route metadata rather than request/user/tenant state, but they must be cleared by worker reload after deploys and must not expand into request-specific caches.

### Static or process-wide state observed

Allowed/low-risk process-wide caches, provided workers are reloaded after deploy/migration:

- `app/Traits/HasCounterCache.php` caches schema column existence.
- `app/Http/Controllers/Activities/EventController.php` caches schema column existence.
- `app/Support/Usernames/UsernameRules.php` caches route reserved segments from route metadata.
- `app/Services/ActiveStatusService.php` caches existence of `users.last_active_at`.
- `app/Models/Pets/Pet.php`, `app/Models/Pets/PetHealthLog.php`, and `app/Models/Identity/User.php` cache schema/table existence.

No application `singleton()` bindings storing request/user/tenant state were found in the audited code paths.

### Request-specific state rule

Never store request, authenticated user, tenant, route, locale, currency, permission, or authorization state in static properties, singletons, long-lived services, global variables, or permanently retained facade-resolved objects. Under Octane, those values can leak to later requests handled by the same worker.

## Eloquent and database performance findings

- `Model::preventLazyLoading(! app()->isProduction())` is already enabled in `AppServiceProvider`.
- Many critical list surfaces already use project-specific eager loading and query-count guidance.
- Seeder `database/seeders/UserBlockSeeder.php` uses `User::all()`. It is acceptable only for small seed datasets; use chunking if seeded user counts grow.
- Comment Livewire components query comment IDs and rehydrate individual cards. This keeps public state compact for lists, but nested component count can still become expensive under heavy comment threads.
- Comment search caps results at 50 and uses `LIKE`; add or validate indexes for `comments(post_id, deleted_at, created_at)`, pinned comments, parent/depth, and quality-score ordering before high-traffic rollout.
- Keep adding query-count tests for feed, profile, comments, search, notifications, and Livewire-heavy routes before considering Octane a performance fix.

## Livewire 4 performance findings and fixes

Fixed in this audit:

- Added `#[Locked]` to immutable IDs in comment Livewire components:
  - `CommentCard::$commentId` and `$postId`
  - `CommentSection::$postId`
  - `CommentReaction::$commentId`
  - `CommentReportModal::$commentId`
  - `CommentInsightsModal::$postId`
  - `ReplyComposer::$postId` and `$parentCommentId`
  - `TopLevelCommentComposer::$postId`

Remaining recommended Livewire work before Octane:

- Replace `CommentCard::$comment` public Eloquent state with a locked ID plus computed/compact view data.
- Keep `render()` methods light. Move any expensive derived data to focused query methods or `#[Computed]` values with explicit invalidation.
- Use `#[Computed(persist: true)]` or `#[Computed(cache: true)]` only when invalidation is explicit and covered by tests.
- Use `#[Lazy]`, `#[Defer]`, `@island`, `#[Async]`, and `#[Renderless]` where they reduce initial payload or avoid unnecessary renders.
- Prefer `.blur`, `.change`, `.debounce`, or `.lazy` for inputs; avoid unnecessary `wire:model.live` on large forms or textareas.
- Keep stable `wire:key` values in loops; the audited comment views already use explicit keys for nested comment/reaction/report components.

## Cache, session, and queue recommendations

- Production Octane: prefer Redis for cache, session, queue, locks, and rate limiting if the infrastructure supports it.
- Database cache/session/queue are acceptable for modest traffic; they can become write-heavy bottlenecks under Octane concurrency.
- Do not run queue workers inside Octane workers. Manage them as separate supervisor processes and run `php artisan queue:restart` on every deploy.
- Horizon is not installed. If it is introduced later, move queue execution to Redis, publish and review `config/horizon.php`, keep supervisor queues aligned with `QueueName::workerOrder()`, protect the dashboard route, and deploy with `php artisan horizon:terminate` so Horizon gracefully restarts workers.
- Avoid serializing large Eloquent models or collections in jobs; pass scalar IDs and re-query in the job handler.
- Keep cache keys user/tenant safe. Include user IDs, tenant IDs, visibility mode, locale, and filters where relevant.
- Do not use `Cache::flush()` on shared production stores. Test-only `Cache::flush()` usage was observed and should remain confined to tests.
- Use Laravel 13 `Cache::memo()` for repeated cache reads inside a single request/job when useful.
- Use `Cache::touch()` when only TTL extension is needed.

## Observability added in this audit

- Added local/staging request memory logging middleware with route, status, duration, memory delta, and peak memory.
- Expanded local/staging query logging with `DB::whenQueryingForLongerThan(500, ...)` for cumulative query-time warnings.
- Existing `/up` health route remains available through Laravel's health route configuration.

Recommended before production Octane:

- Add Laravel Pulse if production performance visibility is needed.
- Add Telescope only in local/staging or a protected environment.
- Track Livewire-heavy routes separately in monitoring.
- Add slow-route and error monitoring through the production logging/APM provider.
- Track worker memory and restart frequency under realistic traffic.

## Octane server choice if blockers are resolved

Do not force one server without deployment confirmation.

- FrankenPHP: simplest operational model if the host supports it; good default for new Laravel deployments that want fewer moving parts.
- RoadRunner: portable process-based server with no PHP extension requirement; useful where installing extensions is hard.
- Swoole/Open Swoole: high performance but extension-based; choose only if the team is comfortable operating and upgrading the extension.

Conservative starting point after installing Octane:

```bash
composer require laravel/octane
php artisan octane:install
php artisan octane:start --server=frankenphp --workers=2 --max-requests=500
```

Tune workers to CPU and memory. Keep max requests conservative until memory profiles are measured.

## Deployment command sequence

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
# If Horizon is later installed:
# php artisan horizon:terminate
# If Reverb is later installed:
# php artisan reverb:restart
# If Octane is later installed and running:
# php artisan octane:reload
curl --fail https://example.com/up
php artisan about --only=environment,cache,database,drivers
```

Production must have `APP_DEBUG=false`.

## Rollback plan

1. Put the app into maintenance mode if user impact is high: `php artisan down --render="errors::503"`.
2. Stop or bypass Octane at the reverse proxy and route traffic back to PHP-FPM/Herd-style workers.
3. Run `php artisan optimize:clear` if cached configuration/routes are suspected.
4. Run `php artisan queue:restart` after rollback code is deployed.
5. If Octane remains enabled, run `php artisan octane:reload` after rollback.
6. Verify `/up`, login, feed, profile, comments, and a representative Livewire action.
7. Bring the app back: `php artisan up`.

## Monitoring checklist

- `/up` health route returns success.
- Error rate and slow-route alerts are active.
- Cumulative query-time warnings are monitored in local/staging before production rollout.
- Memory delta and peak memory logs are reviewed under repeated requests.
- Queue length, failed jobs, and queue worker restarts are monitored separately from Octane.
- Cache/session/queue stores are production-safe and shared across workers.
- Worker reload happens after every release and migration.
- Livewire-heavy routes have query-count and payload-size baselines.
