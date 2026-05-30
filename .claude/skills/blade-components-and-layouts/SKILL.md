---
name: laravel:blade-components-and-layouts
description: Compose UIs with Blade components, slots, and layouts; keep templates pure and testable
---

# Blade Components and Layouts

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.12.0 guidance on PHP 8.5 locally with Composer requiring PHP `^8.4`, Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4.3, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

Encapsulate markup and behavior with components; prefer slots over includes.

## Commands

```
sail artisan make:component Alert              # or: php artisan make:component Alert

// Use component
<x-alert type="warning" :message="$msg" class="mb-4" />

// Layouts + stacks
@extends('layouts.app')
@push('scripts')
    <script>/* page script */</script>
@endpush
```

## Patterns

- Keep components dumb: pass data in, emit markup out
- Use `merge()` to honor passed classes/attributes in components
- Prefer named slots for readability
- Extract small, reusable atoms rather than giant organisms

