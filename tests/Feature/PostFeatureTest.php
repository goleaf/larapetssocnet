<?php

namespace Tests\Feature;

use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_view_and_delete_post(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'body' => 'Hello from PetSocial',
                'visibility' => 'public',
            ])
            ->assertRedirect();

        $post = Post::query()->where('body', 'Hello from PetSocial')->firstOrFail();

        $this->actingAs($user)
            ->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee('Hello from PetSocial');

        $this->actingAs($user)
            ->delete(route('posts.destroy', $post))
            ->assertRedirect();

        $this->assertSoftDeleted('posts', [
            'id' => $post->getKey(),
        ]);
    }
}
