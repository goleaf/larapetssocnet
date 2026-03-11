# Localization + Light UI Workflow

Use this workflow whenever Blade/UI files are created or updated.

## Goals

- Keep translations centralized in `lang/en.php` only.
- Use snake_case keys with array-based PHP translations.
- Keep Blade styling on light palette classes.

## Commands

```bash
composer run i18n:sync
composer run ui:light
vendor/bin/pint --dirty --format agent
php artisan test --compact
```

## Notes

- `i18n:sync` rewrites literal `__('...')` calls to `__('en.some_snake_case_key')` and updates `lang/en.php`.
- `ui:light` normalizes dark Tailwind utility classes in Blade files to light variants.
- If new literal UI text is added in Blade, wrap it in `__()` first so the sync script can migrate it.
