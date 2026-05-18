# Controller Testing Workflow

Use this guide when adding or changing controllers, routes, Form Requests, policies, or route-level tests.

## Required Coverage

- Success path.
- Guest or unauthorized path.
- Validation failure path for mutating requests.
- Persistence side effects.
- Authorization and visibility boundaries.

## Test Style

- Use Pest Feature tests for controller behavior.
- Use factories and `actingAs()` for authenticated flows.
- Prefer `route()` names over hard-coded paths.
- Prefer semantic assertions: `assertOk()`, `assertRedirect()`, `assertForbidden()`, `assertNotFound()`, `assertInvalid()`.
- Avoid raw common HTTP status assertions when a semantic assertion exists.

## Controller Map

Run:

```bash
php scripts/controller-test-map.php --changed
php scripts/controller-test-map.php --all
```

Changed mode is designed for hooks. Strict mode is useful for audits but may require expanding coverage in older modules.
