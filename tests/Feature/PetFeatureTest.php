<?php

namespace Tests\Feature;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            ->assertSee('Mochi')
            ->assertSee('cat')
            ->assertSee('Siamese')
            ->assertSee('Playful rescue cat');
    }

    public function test_pet_profile_page_shows_age_when_birth_date_exists(): void
    {
        $owner = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create([
            'name' => 'Luna',
            'species' => 'dog',
            'breed' => 'Beagle',
            'birth_date' => now()->subYears(4)->toDateString(),
            'bio' => 'Very friendly',
            'is_public' => true,
        ]);

        $this->get(route('pets.show', $pet->getKey()))
            ->assertOk()
            ->assertSee('Age:')
            ->assertSee('years');
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

    public function test_owner_can_upload_pet_gallery_photos_and_view_gallery_tab(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('pets.store'), [
                'name' => 'Gallery Pet',
                'species' => 'cat',
                'breed' => 'Tabby',
                'bio' => 'Gallery bio',
                'is_public' => true,
                'gallery_photos' => [
                    UploadedFile::fake()->image('pet-1.jpg', 800, 600),
                    UploadedFile::fake()->image('pet-2.png', 1024, 768),
                ],
            ])
            ->assertRedirect();

        $pet = Pet::query()->where('name', 'Gallery Pet')->firstOrFail();

        expect($pet->getMedia('gallery'))->toHaveCount(2);

        $this->get(route('pets.show', ['slug' => $pet->getKey(), 'tab' => 'gallery']))
            ->assertOk()
            ->assertSee('<img', false)
            ->assertDontSee('No gallery items yet.');
    }
}
