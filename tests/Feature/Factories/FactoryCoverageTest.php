<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ModelFactoryCoverage;

uses(RefreshDatabase::class);

/*
 * Add factory coverage for a new model:
 * 1. Ensure the model uses HasFactory and has a corresponding factory class.
 * 2. Use required nullable-safe required attributes in the factory definition.
 * 3. Ensure required non-nullable DB fields are present in generated rows.
 * 4. Update tests/Support/FactoryCoverageExclusions.php only when a model is
 *    truly uncreatable (no standalone factory intent), with a plain-language
 *    reason.
 */
it('validates concrete model factory coverage', function (string $modelClass): void {
    $meta = ModelFactoryCoverage::discoverModelMeta($modelClass);
    $exclusions = ModelFactoryCoverage::exclusions();

    expect($meta['is_eloquent'])->toBeTrue('Model must extend Eloquent Model');
    expect($meta['is_abstract'])->toBeFalse('Model must be concrete');
    expect($meta['has_table'])->toBeTrue("{$modelClass} does not map to an existing table");

    expect($meta['required_fields'])->toBeArray();
    expect($meta['unique_fields'])->toBeArray();
    expect($meta['enum_fields'])->toBeArray();
    expect($meta['status_fields'])->toBeArray();

    if (array_key_exists($modelClass, $exclusions)) {
        $reason = trim((string) ($exclusions[$modelClass] ?? ''));

        expect($reason)->not()->toBeEmpty("Exclusion list entry for {$modelClass} has no reason");
        expect($meta['has_factory'])->toBeFalse(
            "{$modelClass} is excluded from coverage but already has a factory; remove it from exclusions."
        );

        return;
    }

    expect($meta['uses_has_factory'])->toBeTrue(
        "{$modelClass} should use HasFactory or be intentionally excluded."
    );
    expect($meta['has_factory'])->toBeTrue(
        "{$modelClass} is discoverable and missing a model factory. Add a factory or document exclusion."
    );

    $created = $modelClass::factory()->create();

    expect($created)->toBeInstanceOf($modelClass);
    expect($created->exists)->toBeTrue();

    $this->assertDatabaseHas($meta['table'], [
        $created->getKeyName() => $created->getKey(),
    ]);

    // Ensure make and create both succeed.
    $made = $modelClass::factory()->make();
    expect($made)->toBeInstanceOf($modelClass);
    expect($made->exists)->toBeFalse();

    // Required non-nullable fields should be populated by factory data.
    foreach ($meta['required_fields'] as $field) {
        expect($created->{$field})->not()->toBeNull();
    }

    // Casts should be safe to hydrate and serialize.
    expect($created->toArray())->toBeArray();

    // Soft delete behavior should be available when model opts in.
    if ($meta['uses_soft_deletes']) {
        $created->delete();
        $this->assertSoftDeleted($meta['table'], [
            $created->getKeyName() => $created->getKey(),
        ]);
        $created->restore();
        expect($created->trashed())->toBeFalse();
    }

    // Media factories should be opt-in only.
    if ($meta['implements_has_media']) {
        expect($created->getMedia())->toHaveCount(0);
    }
})->with(fn () => ModelFactoryCoverage::discoverModels()->all());

it('keeps model factory exclusions explicit and current', function (): void {
    foreach (ModelFactoryCoverage::exclusions() as $excludedClass => $reason) {
        expect($reason)->not()->toBeEmpty("Exclusion reason for {$excludedClass} cannot be empty");

        $meta = ModelFactoryCoverage::discoverModelMeta($excludedClass);

        expect($meta['has_factory'])->toBeFalse(
            "{$excludedClass} is excluded but now has a factory; remove it from exclusions and implement assertions."
        );
    }
});
