<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_user_can_create_text_post(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'body' => 'Hello #Pets',
                'visibility' => 'public',
            ])->assertRedirect();

        $post = Post::query()->latest('id')->first();
        $this->assertNotNull($post);
        $this->assertSame(Post::TYPE_TEXT, $post->type);
        $this->assertSame($user->id, $post->user_id);
        $this->assertNotNull($post->body_html);
    }

    public function test_guest_cannot_create_post(): void
    {
        $this->post(route('posts.store'), [
            'body' => 'No auth',
            'visibility' => 'public',
        ])->assertRedirect();
    }

    public function test_owner_can_update_visibility(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create(['visibility' => Post::VISIBILITY_PUBLIC]);

        $this->actingAs($user)
            ->patch(route('posts.update', $post), [
                'visibility' => Post::VISIBILITY_PRIVATE,
                'body' => $post->body,
            ])->assertRedirect();

        $this->assertSame(Post::VISIBILITY_PRIVATE, $post->fresh()->visibility);
    }
}
