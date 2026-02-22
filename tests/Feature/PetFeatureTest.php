<?php

namespace Tests\Feature;

use App\Models\Pet;
use App\Models\PetHealthLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
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

        if (\Illuminate\Support\Facades\Schema::hasColumn('pets', 'slug')) {
            $this->assertStringContainsString('mochi-'.$user->username, $pet->slug);
        }

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

        $originalSlug = $pet->slug;

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
            ->assertRedirect(route('pets.show', $pet->slug ?? $pet->getKey()));

        $this->assertDatabaseHas('pets', [
            'id' => $pet->id,
            'name' => 'Milo Updated',
            'breed' => 'Labrador',
            'bio' => 'After update',
            'is_public' => 1,
        ]);

        if (\Illuminate\Support\Facades\Schema::hasColumn('pets', 'slug')) {
            $this->assertSame($originalSlug, $pet->fresh()->slug);
        }
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

    public function test_pet_personality_tags_are_saved_and_visible_on_profile(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('pets.store'), [
                'name' => 'Taggy',
                'species' => 'cat',
                'breed' => 'Mixed',
                'bio' => 'Personality test pet',
                'is_public' => true,
                'personality_tags' => 'playful, curious, affectionate',
            ])
            ->assertRedirect();

        $pet = Pet::query()->where('name', 'Taggy')->firstOrFail();

        $this->assertSame(
            ['playful', 'curious', 'affectionate'],
            $pet->personality_tags
        );

        $this->get(route('pets.show', ['slug' => $pet->getKey(), 'tab' => 'about']))
            ->assertOk()
            ->assertSee('Personality')
            ->assertSee('playful')
            ->assertSee('curious')
            ->assertSee('affectionate');
    }

    public function test_adopt_page_shows_only_pets_marked_as_adoptable(): void
    {
        $owner = User::factory()->create();

        $adoptable = Pet::factory()->for($owner)->create([
            'name' => 'Adopt Me',
            'is_public' => true,
        ]);

        $notAdoptable = Pet::factory()->for($owner)->create([
            'name' => 'Not Adoptable',
            'is_public' => true,
        ]);

        if (Schema::hasColumn('pets', 'is_adoptable')) {
            $adoptable->update(['is_adoptable' => true]);
            $notAdoptable->update(['is_adoptable' => false]);
        } elseif (Schema::hasColumn('pets', 'is_for_adoption')) {
            $adoptable->update(['is_for_adoption' => true]);
            $notAdoptable->update(['is_for_adoption' => false]);
        }

        $this->get(route('pets.adopt'))
            ->assertOk()
            ->assertSee($adoptable->name)
            ->assertDontSee($notAdoptable->name);
    }

    public function test_owner_health_tab_shows_weight_history_chart_when_weight_logs_exist(): void
    {
        $owner = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create();

        PetHealthLog::query()->create([
            'pet_id' => $pet->id,
            'logged_by_user_id' => $owner->id,
            'log_type' => 'weight',
            'weight_kg' => 4.2,
            'logged_at' => now()->subDays(3),
        ]);

        PetHealthLog::query()->create([
            'pet_id' => $pet->id,
            'logged_by_user_id' => $owner->id,
            'log_type' => 'weight',
            'weight_kg' => 4.6,
            'logged_at' => now()->subDay(),
        ]);

        $this->actingAs($owner)
            ->get(route('pets.show', ['slug' => $pet->getKey(), 'tab' => 'health']))
            ->assertOk()
            ->assertSee('Weight history')
            ->assertSee('aria-label="Weight history chart"', false);
    }
}
