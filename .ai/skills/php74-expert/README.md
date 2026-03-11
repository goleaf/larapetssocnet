# PHP 7.4 Expert Skill

A comprehensive AI agent skill for PHP 7.4 core development — no framework required.

## What It Covers

- **PHP 7.4 Feature Set** — explicit list of what you can and cannot use (no 8.x features)
- **OOP** — encapsulation, abstraction, composition, polymorphism with real examples
- **SOLID Principles** — all 5, with PHP 7.4 code examples
- **Design Patterns** — Repository, Service Layer, Value Objects, Factory, Strategy, Observer, Decorator, DI Container
- **Security** — prepared statements, XSS prevention, CSRF, password hashing, file upload validation
- **Error Handling** — custom exception hierarchy, global handler, environment-aware responses
- **Performance** — OPcache, N+1 prevention, efficient array operations, caching strategies
- **Input Validation** — chainable validator with typed errors
- **Testing** — PHPUnit patterns with interface-based mocking
- **Project Structure** — PSR-4 directory layout

## Install

```bash
npx skills add cybernerdie/agent-skills --skill php74-expert
```

## Compatible Agents

| Agent | Supported |
|-------|-----------|
| Claude Code | ✅ |
| Cursor | ✅ |
| Windsurf | ✅ |
| Cline | ✅ |
| GitHub Copilot | ✅ |
| Gemini CLI | ✅ |
| Roo | ✅ |
| OpenCode | ✅ |

## Why PHP 7.4 Specifically?

Most PHP skills on skills.sh target PHP 8.2+ or 8.3+. If your project runs on PHP 7.4, those skills will suggest syntax and features your environment doesn't support — named arguments, enums, match expressions, readonly properties, nullsafe operator, and more.

This skill is explicitly constrained to PHP 7.4, so AI agents never suggest code your server can't run.

## License

MIT (in the repository root `LICENSE`)
