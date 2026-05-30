<?php

use App\Models\Identity\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps profile edit actions owner only', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $policy = app(UserPolicy::class);

    expect($policy->editProfile($owner, $owner))->toBeTrue()
        ->and($policy->updateProfile($owner, $owner))->toBeTrue()
        ->and($policy->repositionProfileCover($owner, $owner))->toBeTrue()
        ->and($policy->editProfile($stranger, $owner))->toBeFalse()
        ->and($policy->updateProfile($stranger, $owner))->toBeFalse()
        ->and($policy->repositionProfileCover($stranger, $owner))->toBeFalse();
});
