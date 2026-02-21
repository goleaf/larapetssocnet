<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_and_join_group(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('groups.store'), [
                'name' => 'Dog Walk Crew',
                'description' => 'Weekend neighborhood walks.',
                'privacy' => 'public',
            ])
            ->assertRedirect();

        $group = Group::query()->where('name', 'Dog Walk Crew')->firstOrFail();

        $this->actingAs($member)
            ->post(route('groups.join', $group->slug))
            ->assertRedirect();

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->getKey(),
            'user_id' => $member->getKey(),
            'status' => 'active',
        ]);
    }
}
