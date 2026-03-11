<?php

use App\Models\UsernameChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates username change history records with casts and relations', function (): void {
    $change = UsernameChange::factory()->create();

    expect($change->old_username)->not->toBeEmpty();
    expect($change->new_username)->not->toBeEmpty();
    expect($change->changed_at)->toBeInstanceOf(Carbon::class);
    expect($change->user)->not->toBeNull();
});
