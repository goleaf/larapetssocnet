---
name: laravel:api-surface-evolution
description: Evolve APIs safely using versioned DTOs/transformers, deprecations, and compatibility tests
---

# API Surface Evolution

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.12.0 guidance on PHP 8.4+ with Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4.3, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

Design for change without breaking clients.

## Versioning Strategy

- Choose explicit versioning (URI `/v1/...` or header negotiation)
- Default to additive changes; never break a released contract

## DTOs & Transformers

- Define versioned DTOs; map from models/services via transformers
- Keep controller thin—validate → transform → respond

## Deprecations

- Mark fields as deprecated in docs and responses (e.g., headers)
- Provide sunset timelines; add metrics to see remaining usage

## Testing

- Contract tests per version (request/response shapes)
- Backward compatibility tests for commonly used flows

