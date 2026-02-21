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
                'bio' => 'Playful rescue cat',
                'is_public' => true,
            ])
            ->assertRedirect();

        $pet = Pet::query()->where('name', 'Mochi')->firstOrFail();

        $this->get(route('pets.show', $pet->getKey()))
            ->assertOk()
            ->assertSee('Mochi');
    }
}
