<?php

use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('allows owner to update and remove pet avatar', function (): void {
    Storage::fake('public');

    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->post(route('pets.avatar.store', $pet), [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 300, 300),
        ])
        ->assertRedirect();

    expect($pet->fresh()->getFirstMedia('avatar'))->not()->toBeNull();

    $this->actingAs($owner)
        ->delete(route('pets.avatar.destroy', $pet))
        ->assertRedirect();

    expect($pet->fresh()->getFirstMedia('avatar'))->toBeNull();
});

it('prevents non-owners from managing pet avatar', function (): void {
    Storage::fake('public');

    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->post(route('pets.avatar.store', $pet), [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 300, 300),
        ])
        ->assertForbidden();
});
