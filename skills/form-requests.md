# Form Requests

## Location and naming
- `app/Http/Requests/`
- `{Verb}{Resource}Request.php`

## Rules
- One Form Request per write action.
- `authorize()` must enforce policy checks.
- `rules()` should be explicit and type-safe.
- `messages()` should provide human-readable errors.
- `prepareForValidation()` should normalize input where needed.
