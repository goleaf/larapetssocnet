<?php

namespace Tests\Feature;

use App\Models\Content\Post;
use App\Models\Groups\Group;
use App\Models\Identity\User;
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

    public function test_owner_can_publish_and_remove_group_post_with_counter_sync(): void
    {
        $owner = User::factory()->create();
        $group = Group::factory()->create([
            'owner_user_id' => $owner->getKey(),
            'owner_id' => $owner->getKey(),
            'posts_count' => 0,
        ]);

        $this->actingAs($owner)
            ->post(route('groups.posts.store', $group->slug), [
                'body' => 'group post body',
            ])
            ->assertRedirect();

        $post = Post::query()
            ->where('group_id', $group->getKey())
            ->where('body', 'group post body')
            ->firstOrFail();

        $this->assertDatabaseHas('group_posts', [
            'group_id' => $group->getKey(),
            'post_id' => $post->getKey(),
            'added_by_user_id' => $owner->getKey(),
        ]);

        $group->refresh();
        $this->assertSame(1, (int) $group->posts_count);

        $this->actingAs($owner)
            ->delete(route('groups.posts.destroy', [$group->slug, $post->getKey()]))
            ->assertRedirect();

        $this->assertSoftDeleted('posts', [
            'id' => $post->getKey(),
        ]);
        $this->assertDatabaseMissing('group_posts', [
            'group_id' => $group->getKey(),
            'post_id' => $post->getKey(),
        ]);

        $group->refresh();
        $this->assertSame(0, (int) $group->posts_count);
    }
}
