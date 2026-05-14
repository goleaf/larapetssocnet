<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('removes selected relations from a clone without mutating the original model', function (): void {
    $user = User::factory()->create();

    Post::factory()->create([
        'user_id' => $user->id,
        'visibility' => 'public',
    ]);

    $user->load('posts', 'followers');

    expect($user->relationLoaded('posts'))->toBeTrue();
    expect($user->relationLoaded('followers'))->toBeTrue();

    $relationLightClone = $user->withoutRelation(['posts', 'followers']);

    expect($relationLightClone->relationLoaded('posts'))->toBeFalse();
    expect($relationLightClone->relationLoaded('followers'))->toBeFalse();

    expect($user->relationLoaded('posts'))->toBeTrue();
    expect($user->relationLoaded('followers'))->toBeTrue();
});
