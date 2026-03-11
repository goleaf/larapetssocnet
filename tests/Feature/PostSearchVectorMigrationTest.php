<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('defines a postgres tsvector column for posts search indexing', function (): void {
    $migrationContents = File::get(database_path('migrations/2026_03_11_204621_add_search_vector_column_to_posts_table.php'));

    expect($migrationContents)
        ->toContain("tsvector('search_vector')")
        ->toContain("'posts_search_vector_index', 'gin'");
});

it('keeps non-postgresql schemas unchanged when running the search vector migration', function (): void {
    if (Schema::getConnection()->getDriverName() === 'pgsql') {
        $this->markTestSkipped('This assertion is intended for non-PostgreSQL connections.');
    }

    expect(Schema::hasColumn('posts', 'search_vector'))->toBeFalse();

    $migration = require database_path('migrations/2026_03_11_204621_add_search_vector_column_to_posts_table.php');
    $migration->up();

    expect(Schema::hasColumn('posts', 'search_vector'))->toBeFalse();

    $migration->down();

    expect(Schema::hasColumn('posts', 'search_vector'))->toBeFalse();
});
