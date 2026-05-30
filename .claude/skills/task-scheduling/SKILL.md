---
name: laravel:task-scheduling
description: Schedule tasks with safety; use withoutOverlapping, onOneServer, and visibility settings for reliable cron execution
---

# Task Scheduling

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.12.0 guidance on PHP 8.5 locally with Composer requiring PHP `^8.4`, Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4.3, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

Run scheduled tasks predictably across environments.

## Commands

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('reports:daily')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->evenInMaintenanceMode();
```

```shell
# Run the scheduler from cron
* * * * * cd /var/www/app && php artisan schedule:run >> /dev/null 2>&1
```

## Patterns

- Define schedules in `routes/console.php` or use `withSchedule` in `bootstrap/app.php`; do not recreate the removed console kernel.
- Guard long-running commands with `withoutOverlapping()`
- Use `onOneServer()` when running on multiple nodes
- Emit logs/metrics for visibility; consider notifications on failure
- Feature-flag risky jobs via config/env
