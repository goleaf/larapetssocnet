---
name: laravel:controller-tests
description: Write focused controller tests using HTTP assertions; keep heavy logic in Actions/Services and unit test them
---

# Controller Tests

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.9 guidance on PHP 8.4 with Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

## Feature tests for endpoints

```php
it('rejects empty email', function () {
  $this->post('/register', ['email' => ''])->assertSessionHasErrors('email');
});
```

## Better tests

- Move validation to Form Requests; assert errors from the request class
- Extract business logic into Actions; unit test them directly
- Use factories for realistic data; avoid heavy mocking

