# Controller Testing

The repository currently has 70 concrete controllers and a large Pest suite. Controller coverage should be maintained through feature tests that exercise routes and user-visible behavior instead of calling controller methods directly.

## Expectations

- Each new controller gets at least one Feature test for a success path and one denial or validation path.
- Each changed controller should have an existing or changed related test detectable by `scripts/controller-test-map.php`.
- Protected routes must prove guest redirect/auth denial and allowed authenticated access.
- Mutating routes must prove validation, authorization, persisted side effects, and important events/notifications when applicable.
- JSON-capable endpoints should assert JSON shape and semantic status helpers.

## Route Test Matrix

For each controller route family, test:

- guest behavior,
- authenticated allowed behavior,
- unauthorized/forbidden behavior,
- validation failure behavior,
- persistence side effects,
- response copy or view data when user-facing.

## Naming

Prefer Feature test files by domain:

- `tests/Feature/Messages/MessageThreadTest.php`
- `tests/Feature/Groups/GroupArchiveFeatureTest.php`
- `tests/Feature/Pets/PetFollowFeatureTest.php`

Use route names and visible copy in assertions so tests remain meaningful after refactors.

## Hooks

Install hooks with `bash scripts/install-git-hooks.sh`. The hooks run a changed-controller coverage guard before allowing commits and pushes.
