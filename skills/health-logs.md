# Health Logs

Health logs belong to a pet and are stored in `pet_health_logs`.

## Types
- `weight`
- `vet_visit`
- `vaccination` (legacy `vaccine` is normalized)
- `medication`

## Fields
- `pet_id`, `logged_by_user_id`
- `log_type`, `title`, `notes`
- `weight_kg` nullable
- `temperature_c` nullable
- `logged_at`
- `next_due_at` nullable

## Queries
- `PetHealthLog::paginateForPet($pet, $perPage = 15)`
- `PetHealthLog::upcomingForPet($pet, $limit = 10)`
- `PetHealthLog::weightTrendForPet($pet, $limit = 30)`

## Ownership
- Access is controlled via `PetPolicy::update` (see `PetHealthLogController`).
