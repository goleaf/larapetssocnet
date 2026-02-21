<?php

namespace Tests\Feature;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PetFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_and_view_pet_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('pets.store'), [
                'name' => 'Mochi',
                'species' => 'cat',
                'breed' => 'Siamese',
                'sex' => 'female',
                'bio' => 'Playful rescue cat',
                'is_public' => true,
                'is_adoptable' => true,
            ])
            ->assertRedirect();

        $pet = Pet::query()->where('name', 'Mochi')->firstOrFail();

        $this->get(route('pets.show', $pet->getKey()))
            ->assertOk()
            ->assertSee('Mochi');
    }

    public function test_owner_can_edit_pet_profile(): void
    {
        $owner = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create([
            'name' => 'Milo',
            'species' => 'dog',
            'breed' => 'Mixed',
            'sex' => 'male',
            'bio' => 'Before update',
            'is_public' => true,
        ]);

        $this->actingAs($owner)
            ->patch(route('pets.update', $pet->getKey()), [
                'name' => 'Milo Updated',
                'species' => 'dog',
                'breed' => 'Labrador',
                'sex' => 'male',
                'birth_date' => now()->subYears(3)->toDateString(),
                'bio' => 'After update',
                'is_public' => '1',
            ])
            ->assertRedirect(route('pets.show', $pet->getKey()));

        $this->assertDatabaseHas('pets', [
            'id' => $pet->id,
            'name' => 'Milo Updated',
            'breed' => 'Labrador',
            'bio' => 'After update',
            'is_public' => 1,
        ]);
    }

    public function test_non_owner_cannot_edit_pet_profile(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->patch(route('pets.update', $pet->getKey()), [
                'name' => 'Hacked Name',
                'species' => $pet->species,
            ])
            ->assertForbidden();
    }
}
