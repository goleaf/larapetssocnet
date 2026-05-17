---
name: laravel:tdd-with-pest
description: Apply RED-GREEN-REFACTOR with Pest; use factories, feature tests for HTTP, and parallel test runners; verify failures before implementation
---

# Laravel TDD with Pest

Write the test first. Watch it fail. Write minimal code to pass. Keep tests fast and realistic.

## Test Runner

```
# Sail
sail artisan test --parallel

# Non-Sail
php artisan test --parallel
```

Write new and changed tests with Pest for this project.

## RED – Write a failing test

- Use model factories; avoid heavy mocking
- Feature tests for HTTP/controllers; unit tests for pure services
- Name tests by behavior

Example (feature):

```php
it('rejects empty email on signup', function () {
    $response = $this->post('/register', [
        'name' => 'Alice',
        'email' => '',
        'password' => 'secret',
        'password_confirmation' => 'secret',
    ]);

    $response->assertSessionHasErrors('email');
});
```

Run and confirm it fails for the right reason.

## GREEN – Minimal implementation

Write the simplest code to pass your failing test. No extras. No refactors.

## REFACTOR – Clean up

Remove duplication, clarify names, extract small services. Keep tests green.

## Guidelines

- Every production change starts with a failing test
- Watch failures; never skip the failing phase
- Use factories/seeders for realistic data
- Use DB transactions or refreshes in tests as needed
- Keep tests deterministic; avoid sleeps in async flows

## When stuck

- If a test is hard to write, design is too coupled; extract a service or port
- Prefer dependency injection; avoid static/global state
