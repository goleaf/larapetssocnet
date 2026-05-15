<?php

declare(strict_types=1);

use App\Models\Identity\UsernameChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('creates username change history records with casts and relations', function (): void {
    $change = UsernameChange::factory()->create();

    expect($change->old_username)->not->toBeEmpty();
    expect($change->new_username)->not->toBeEmpty();
    expect($change->changed_at)->toBeInstanceOf(Carbon::class);
    expect($change->user)->not->toBeNull();
});
