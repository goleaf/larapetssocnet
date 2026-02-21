<?php

namespace Tests\Feature;

use App\Models\Pet;
use App\Models\PetHealthLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PetHealthLogFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_weight_vet_visit_vaccination_and_medication_logs(): void
    {
        $owner = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create();

        $this->actingAs($owner)->post(route('pets.health.store', $pet->id), [
            'type' => 'weight',
            'value' => 12.4,
            'title' => 'Weekly weight',
            'notes' => 'Stable',
            'logged_at' => now()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($owner)->post(route('pets.health.store', $pet->id), [
            'type' => 'vet_visit',
            'title' => 'Annual checkup',
            'notes' => 'All good',
            'logged_at' => now()->subDay()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($owner)->post(route('pets.health.store', $pet->id), [
            'type' => 'vaccination',
            'title' => 'Rabies shot',
            'notes' => 'Next due in 12 months',
            'logged_at' => now()->subDays(2)->toDateString(),
        ])->assertRedirect();

        $this->actingAs($owner)->post(route('pets.health.store', $pet->id), [
            'type' => 'medication',
            'title' => 'Antibiotic course',
            'notes' => 'Twice daily',
            'logged_at' => now()->subDays(3)->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('pet_health_logs', ['pet_id' => $pet->id, 'log_type' => 'weight']);
        $this->assertDatabaseHas('pet_health_logs', ['pet_id' => $pet->id, 'log_type' => 'vet_visit']);
        $this->assertDatabaseHas('pet_health_logs', ['pet_id' => $pet->id, 'log_type' => 'vaccination']);
        $this->assertDatabaseHas('pet_health_logs', ['pet_id' => $pet->id, 'log_type' => 'medication']);
    }

    public function test_non_owner_cannot_manage_health_logs(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('pets.health.index', $pet->id))
            ->assertNotFound();

        $this->actingAs($other)
            ->post(route('pets.health.store', $pet->id), [
                'type' => 'weight',
                'value' => 10,
                'logged_at' => now()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_owner_can_update_and_delete_health_log(): void
    {
        $owner = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create();
        $log = PetHealthLog::query()->create([
            'pet_id' => $pet->id,
            'logged_by_user_id' => $owner->id,
            'log_type' => 'medication',
            'title' => 'Old medication',
            'notes' => 'Old notes',
            'logged_at' => now()->subDay(),
        ]);

        $this->actingAs($owner)
            ->patch(route('pets.health.update', ['slug' => $pet->id, 'healthLog' => $log->id]), [
                'type' => 'vet_visit',
                'title' => 'Updated title',
                'notes' => 'Updated notes',
                'logged_at' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pet_health_logs', [
            'id' => $log->id,
            'log_type' => 'vet_visit',
            'title' => 'Updated title',
        ]);

        $this->actingAs($owner)
            ->delete(route('pets.health.destroy', ['slug' => $pet->id, 'healthLog' => $log->id]))
            ->assertRedirect();

        $this->assertSoftDeleted('pet_health_logs', [
            'id' => $log->id,
        ]);
    }
}
