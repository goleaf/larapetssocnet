<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('uses the composite jobs queue polling index from laravel 12.54+', function (): void {
    $indexesByName = collect(Schema::getIndexes('jobs'))->keyBy('name');

    expect($indexesByName->has('jobs_queue_reserved_at_available_at_index'))->toBeTrue();
    expect($indexesByName->has('jobs_queue_index'))->toBeFalse();

    $compositeIndex = $indexesByName->get('jobs_queue_reserved_at_available_at_index');

    expect($compositeIndex)->toBeArray();
    expect($compositeIndex['columns'])->toBe(['queue', 'reserved_at', 'available_at']);
});
