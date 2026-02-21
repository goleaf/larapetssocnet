<?php

namespace Tests\Feature;

use App\Models\Hashtag;
use App\Models\Pet;
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

    public function test_hashtags_are_auto_detected_from_post_body_on_create(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'body' => 'Loving #Cats and #Dogs in #cats community',
                'visibility' => Post::VISIBILITY_PUBLIC,
            ])->assertRedirect();

        $post = Post::query()->latest('id')->firstOrFail();

        $tagNames = $post->hashtags()->pluck('name')->sort()->values()->all();
        $this->assertSame(['cats', 'dogs'], $tagNames);

        $this->assertDatabaseHas('hashtags', ['name' => 'cats']);
        $this->assertDatabaseHas('hashtags', ['name' => 'dogs']);
        $this->assertDatabaseHas('post_hashtag', ['post_id' => $post->id, 'hashtag_id' => Hashtag::query()->where('name', 'cats')->value('id')]);
    }

    public function test_hashtags_resync_when_post_body_changes(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create([
            'body' => 'First #cats',
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);

        app(\App\Services\HashtagService::class)->syncHashtags($post);
        $this->assertSame(['cats'], $post->fresh()->hashtags()->pluck('name')->all());

        $this->actingAs($user)
            ->patch(route('posts.update', $post), [
                'body' => 'Now #dogs only',
                'visibility' => Post::VISIBILITY_PUBLIC,
            ])->assertRedirect();

        $post->refresh();
        $tagNames = $post->hashtags()->pluck('name')->sort()->values()->all();
        $this->assertSame(['dogs'], $tagNames);
    }

    public function test_user_can_tag_pets_on_post_create_and_render_them_on_post_page(): void
    {
        $user = User::factory()->create();
        $petOne = Pet::factory()->for($user)->create(['name' => 'Milo']);
        $petTwo = Pet::factory()->for($user)->create(['name' => 'Luna']);

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'body' => 'Tagged pets post',
                'visibility' => Post::VISIBILITY_PUBLIC,
                'tagged_pets' => [$petOne->id, $petTwo->id],
            ])->assertRedirect();

        $post = Post::query()->latest('id')->firstOrFail();

        $this->assertSame([$petOne->id, $petTwo->id], $post->tagged_pets);
        $this->assertSame($petOne->id, $post->pet_id);

        $this->actingAs($user)
            ->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee('Tagged Pets')
            ->assertSee('Milo')
            ->assertSee('Luna');
    }

    public function test_user_can_update_tagged_pets_on_post(): void
    {
        $user = User::factory()->create();
        $petOne = Pet::factory()->for($user)->create(['name' => 'Milo']);
        $petTwo = Pet::factory()->for($user)->create(['name' => 'Luna']);
        $post = Post::factory()->for($user)->create([
            'body' => 'Before retag',
            'visibility' => Post::VISIBILITY_PUBLIC,
            'tagged_pets' => [$petOne->id],
            'pet_id' => $petOne->id,
        ]);

        $this->actingAs($user)
            ->patch(route('posts.update', $post), [
                'body' => 'After retag',
                'visibility' => Post::VISIBILITY_PUBLIC,
                'tagged_pets' => [$petTwo->id],
                'pet_id' => $petTwo->id,
            ])->assertRedirect(route('posts.show', $post));

        $post->refresh();
        $this->assertSame([$petTwo->id], $post->tagged_pets);
        $this->assertSame($petTwo->id, $post->pet_id);
    }
}
