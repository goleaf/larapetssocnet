---
name: laravel:strategy-pattern
description: Use the Strategy pattern to select behavior at runtime; bind multiple implementations to a shared interface
---

# Strategy Pattern

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.12.0 guidance on PHP 8.4+ with Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4.3, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

Create a common interface and multiple implementations. Choose a strategy by key or context.

```php
interface TaxCalculator { public function for(int $cents): int; }
final class NzTax implements TaxCalculator { /* ... */ }
final class AuTax implements TaxCalculator { /* ... */ }

final class TaxFactory {
  public function __construct(private array $drivers) {}
  public function forCountry(string $code): TaxCalculator { return $this->drivers[$code]; }
}
```

Register in a service provider and inject the factory where needed.

