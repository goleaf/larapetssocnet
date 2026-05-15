<?php

namespace Tests\Feature;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetHealthLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PetHealthLogFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_weight_vet_visit_vaccination_and_medication_logs(): void
    {
        $owner = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create();

        $this->actingAs($owner)->post(route('pets.health.store', $pet), [
            'type' => 'weight',
            'value' => 12.4,
            'title' => 'Weekly weight',
            'notes' => 'Stable',
            'logged_at' => now()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($owner)->post(route('pets.health.store', $pet), [
            'type' => 'vet_visit',
            'title' => 'Annual checkup',
            'notes' => 'All good',
            'logged_at' => now()->subDay()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($owner)->post(route('pets.health.store', $pet), [
            'type' => 'vaccination',
            'title' => 'Rabies shot',
            'notes' => 'Next due in 12 months',
            'logged_at' => now()->subDays(2)->toDateString(),
            'next_due_at' => now()->addMonths(12)->toDateString(),
        ])->assertRedirect();

        $this->actingAs($owner)->post(route('pets.health.store', $pet), [
            'type' => 'medication',
            'title' => 'Antibiotic course',
            'notes' => 'Twice daily',
            'logged_at' => now()->subDays(3)->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('pet_health_logs', ['pet_id' => $pet->id, 'log_type' => 'weight']);
        $this->assertDatabaseHas('pet_health_logs', ['pet_id' => $pet->id, 'log_type' => 'vet_visit']);
        $this->assertDatabaseHas('pet_health_logs', ['pet_id' => $pet->id, 'log_type' => 'vaccination']);
        $this->assertDatabaseHas('pet_health_logs', ['pet_id' => $pet->id, 'log_type' => 'medication']);
        $this->assertDatabaseHas('pet_health_logs', ['pet_id' => $pet->id, 'log_type' => 'vaccination']);
    }

    public function test_non_owner_cannot_manage_health_logs(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('pets.health.index', $pet))
            ->assertForbidden();

        $this->actingAs($other)
            ->post(route('pets.health.store', $pet), [
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
            ->patch(route('pets.health.update', ['pet' => $pet, 'healthLog' => $log->id]), [
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
            ->delete(route('pets.health.destroy', ['pet' => $pet, 'healthLog' => $log->id]))
            ->assertRedirect();

        $this->assertSoftDeleted('pet_health_logs', [
            'id' => $log->id,
        ]);
    }

    public function test_owner_can_set_next_due_date_with_interval_input(): void
    {
        $owner = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create();
        $loggedAt = now()->toDateString();

        $this->actingAs($owner)
            ->post(route('pets.health.store', $pet), [
                'type' => 'medication',
                'title' => 'Deworming',
                'notes' => 'Monthly schedule',
                'logged_at' => $loggedAt,
                'next_due_in' => 2,
                'next_due_unit' => 'weeks',
            ])
            ->assertRedirect();

        /** @var PetHealthLog $createdLog */
        $createdLog = PetHealthLog::query()
            ->where('pet_id', $pet->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertNotNull($createdLog->next_due_at);
        $this->assertSame(
            Carbon::parse($loggedAt)->addWeeks(2)->toDateString(),
            $createdLog->next_due_at->toDateString(),
        );
    }

    public function test_owner_can_set_next_due_date_with_iso_interval_input(): void
    {
        $owner = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create();
        $loggedAt = now()->toDateString();

        $this->actingAs($owner)
            ->post(route('pets.health.store', $pet), [
                'type' => 'medication',
                'title' => 'Heartworm prevention',
                'notes' => 'ISO interval schedule',
                'logged_at' => $loggedAt,
                'next_due_interval' => 'P10D',
            ])
            ->assertRedirect();

        /** @var PetHealthLog $createdLog */
        $createdLog = PetHealthLog::query()
            ->where('pet_id', $pet->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertNotNull($createdLog->next_due_at);
        $this->assertSame(
            Carbon::parse($loggedAt)->addDays(10)->toDateString(),
            $createdLog->next_due_at->toDateString(),
        );
    }

    public function test_next_due_date_and_interval_inputs_are_mutually_exclusive(): void
    {
        $owner = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->post(route('pets.health.store', $pet), [
                'type' => 'vaccination',
                'title' => 'Parvo shot',
                'logged_at' => now()->toDateString(),
                'next_due_at' => now()->addMonths(12)->toDateString(),
                'next_due_in' => 30,
                'next_due_unit' => 'days',
            ])
            ->assertSessionHasErrors(['next_due_at', 'next_due_in']);
    }

    public function test_upcoming_reminders_are_driven_by_next_due_date_and_sorted_ascending(): void
    {
        $owner = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create();

        PetHealthLog::query()->create([
            'pet_id' => $pet->id,
            'logged_by_user_id' => $owner->id,
            'log_type' => 'vaccination',
            'title' => 'Later reminder',
            'notes' => null,
            'logged_at' => now()->subDays(10),
            'next_due_at' => now()->addDays(10),
        ]);

        PetHealthLog::query()->create([
            'pet_id' => $pet->id,
            'logged_by_user_id' => $owner->id,
            'log_type' => 'medication',
            'title' => 'Soon reminder',
            'notes' => null,
            'logged_at' => now()->subDays(5),
            'next_due_at' => now()->addDays(2),
        ]);

        $response = $this->actingAs($owner)
            ->get(route('pets.health.index', $pet))
            ->assertOk();

        $titles = collect($response->viewData('upcomingLogs'))
            ->pluck('title')
            ->values()
            ->all();
        $this->assertSame(['Soon reminder', 'Later reminder'], $titles);
    }
}
