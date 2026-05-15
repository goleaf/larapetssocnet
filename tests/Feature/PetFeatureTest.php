<?php

namespace Tests\Feature;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetHealthLog;
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

        if (Schema::hasColumn('pets', 'slug')) {
            $this->assertStringStartsWith('mochi', (string) $pet->slug);
        }

        $this->get(route('pets.show', $pet))
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

        $this->get(route('pets.show', $pet))
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
            ->patch(route('pets.update', $pet), [
                'name' => 'Milo Updated',
                'species' => 'dog',
                'breed' => 'Labrador',
                'sex' => 'male',
                'birth_date' => now()->subYears(3)->toDateString(),
                'bio' => 'After update',
                'is_public' => '1',
            ])
            ->assertRedirect(route('pets.show', $pet));

        $this->assertDatabaseHas('pets', [
            'id' => $pet->id,
            'name' => 'Milo Updated',
            'breed' => 'Labrador',
            'bio' => 'After update',
            'is_public' => 1,
        ]);

        if (Schema::hasColumn('pets', 'slug')) {
            $this->assertSame($originalSlug, $pet->fresh()->slug);
        }
    }

    public function test_non_owner_cannot_edit_pet_profile(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->patch(route('pets.update', $pet), [
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

        $this->get(route('pets.show', ['pet' => $pet, 'tab' => 'gallery']))
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

        $this->get(route('pets.show', ['pet' => $pet, 'tab' => 'about']))
            ->assertOk()
            ->assertSee('Personality')
            ->assertSee('Playful')
            ->assertSee('Curious')
            ->assertSee('Affectionate');
    }

    public function test_adopt_page_shows_only_pets_marked_as_adoptable(): void
    {
        $owner = User::factory()->create();

        $adoptable = Pet::factory()->for($owner)->create([
            'name' => 'Adopt Me',
            'is_public' => true,
        ]);

        $privateAdoptable = Pet::factory()->for($owner)->create([
            'name' => 'Private Adoptable',
            'is_public' => false,
        ]);

        $notAdoptable = Pet::factory()->for($owner)->create([
            'name' => 'Not Adoptable',
            'is_public' => true,
        ]);

        if (Schema::hasColumn('pets', 'is_adoptable')) {
            $adoptable->update(['is_adoptable' => true]);
            $privateAdoptable->update(['is_adoptable' => true]);
            $notAdoptable->update(['is_adoptable' => false]);
        } elseif (Schema::hasColumn('pets', 'is_for_adoption')) {
            $adoptable->update(['is_for_adoption' => true]);
            $privateAdoptable->update(['is_for_adoption' => true]);
            $notAdoptable->update(['is_for_adoption' => false]);
        }

        $this->get(route('pets.adopt'))
            ->assertOk()
            ->assertSee($adoptable->name)
            ->assertDontSee($privateAdoptable->name)
            ->assertDontSee($notAdoptable->name);
    }

    public function test_owner_can_update_personality_tags(): void
    {
        $owner = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create([
            'name' => 'Sunny',
            'is_public' => true,
        ]);

        $this->actingAs($owner)
            ->patch(route('pets.update', $pet), [
                'name' => 'Sunny',
                'species' => $pet->species,
                'personality_tags' => ['Playful', ' Calm ', 'playful'],
            ])
            ->assertRedirect();

        $this->assertSame(['playful', 'calm'], $pet->fresh()->personality_tags);
    }

    public function test_pet_profile_shows_adoptable_badge_only_when_true(): void
    {
        $owner = User::factory()->create();
        $adoptable = Pet::factory()->for($owner)->create([
            'name' => 'Badge Pet',
            'is_public' => true,
            'is_adoptable' => true,
        ]);

        $notAdoptable = Pet::factory()->for($owner)->create([
            'name' => 'No Badge',
            'is_public' => true,
            'is_adoptable' => false,
        ]);

        $this->get(route('pets.show', $adoptable))
            ->assertOk()
            ->assertSee(__('pets.status.adoptable'));

        $this->get(route('pets.show', $notAdoptable))
            ->assertOk()
            ->assertDontSee(__('pets.status.adoptable'));
    }

    public function test_owner_can_toggle_adoptable_flag(): void
    {
        $owner = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create([
            'name' => 'Toggle Pet',
            'is_public' => true,
            'is_adoptable' => false,
        ]);

        $this->actingAs($owner)
            ->patch(route('pets.update', $pet), [
                'name' => 'Toggle Pet',
                'species' => $pet->species,
                'is_adoptable' => '1',
            ])
            ->assertRedirect();

        $this->assertTrue((bool) $pet->fresh()->is_adoptable);
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
            ->get(route('pets.show', ['pet' => $pet, 'tab' => 'health']))
            ->assertOk()
            ->assertSee('Weight history')
            ->assertSee('aria-label="Weight history chart"', false);
    }
}
