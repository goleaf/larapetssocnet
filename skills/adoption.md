# Adoption

Adoption is status-driven on the pet profile.

## Status
- `not_listed`
- `available`
- `pending`
- `adopted`

## Listing fields
- `adoption_fee` nullable unsigned int (`0` means free)
- `adoption_notes` nullable text
- `adoption_contact` nullable string
- `adoption_listed_at` timestamp when set `available`

## Browse page
- Public route: `/adoption`
- Filters: species, size, location text, free/any fee
- Sort: newest listed (v1)
- Each card links to pet profile

## Transitions
Allowed:
- `not_listed -> available`
- `available -> pending`
- `available -> not_listed`
- `pending -> available`
- `pending -> adopted`
- `adopted -> not_listed`

`AdoptionService` owns transition validation and persistence.
