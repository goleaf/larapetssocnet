<?php

use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the post create form with available pets', function (): void {
    $user = User::factory()->create();
    $pet = Pet::factory()->create([
        'user_id' => $user->id,
        'name' => 'Biscuit',
    ]);

    $this->actingAs($user)
        ->get(route('posts.create'))
        ->assertOk()
        ->assertSee($pet->name);
});
