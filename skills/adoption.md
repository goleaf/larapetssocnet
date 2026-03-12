# Adoption

Adoption is status-driven on the pet profile.

## Status
- `not_listed`
- `available`
- `pending`
- `adopted`

## Listing Fields
- `adoption_fee` nullable integer (`0` means free)
- `adoption_notes` nullable text
- `adoption_contact` nullable string
- `adoption_listed_at` timestamp when set `available`

## Browse Page
- Public route: `/adoption`
- Filters: species, size, location text, free/any fee
- Sort: newest listed (`adoption_listed_at DESC`)
- Query owned by `AdoptionService::getListings()`.

## Transitions
Allowed (see `AdoptionService::TRANSITIONS`):
- `not_listed -> available`
- `available -> pending`
- `available -> not_listed`
- `pending -> available`
- `pending -> adopted`
- `adopted -> not_listed`

`AdoptionService::setStatus()` validates and persists transitions.
