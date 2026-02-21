# Personality Tags

Free-form pet labels stored in `pet_personality_tags`.

## Constraints
- Max 10 tags per pet.
- Tag max length 30.
- Unique per pet.
- Sanitize to lowercase alphanumeric + spaces.

## Suggestions
Suggested list is provided in service and UI, but custom tags are allowed.

## UX
- Alpine tag input with suggestions dropdown.
- Tag chips with remove action.
- Hidden `tags[]` inputs for submit.

`PersonalityTagService` owns validation/sync/suggestions.
