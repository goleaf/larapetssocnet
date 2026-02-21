# Health Logs

Health logs belong to a pet.

## Types
- `weight`
- `vet_visit`
- `vaccination`
- `medication`
- `note`

## Fields
- `pet_id`, `type`, `title`
- `value` nullable numeric
- `unit` nullable string
- `notes` nullable text
- `log_date`
- `next_due_date` nullable

## Reminders (UI-only)
- No queue/email jobs in v1.
- Show reminders due in next 30 days.
- Show urgent badge if due in 7 days.

## Views
- Logs paginated (20/page).
- Tabs: All, Weight, Vet, Vaccines, Medication, Notes.

## Ownership
- `HealthLogService` owns CRUD.
- `HealthLogPolicy` is owner-only.
