---
name: laravel:interfaces-and-di
description: Use interfaces and dependency injection to decouple code; bind implementations in the container
---

# Interfaces and Dependency Injection

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.9 guidance on PHP 8.4 with Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

Define narrow interfaces and inject them where needed. Bind concrete implementations in a service provider.

```php
interface Slugger { public function slug(string $s): string; }

final class AsciiSlugger implements Slugger {
  public function slug(string $s): string { /* ... */ }
}

$this->app->bind(Slugger::class, AsciiSlugger::class);
```

Benefits: easier testing (mock interfaces), clearer contracts, swap implementations without touching consumers.

