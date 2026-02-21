<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_upload_single_image_to_post(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'body' => null,
                'visibility' => 'public',
                'photos' => [UploadedFile::fake()->image('photo.jpg', 800, 600)],
            ])->assertRedirect();

        $post = Post::query()->latest('id')->firstOrFail();

        $this->assertSame(Post::TYPE_PHOTO, $post->type);
        $this->assertCount(1, $post->getMedia('photos'));
    }

    public function test_can_upload_video_to_post(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'body' => null,
                'visibility' => 'public',
                'video' => UploadedFile::fake()->create('clip.mp4', 1024, 'video/mp4'),
            ])->assertRedirect();

        $post = Post::query()->latest('id')->firstOrFail();

        $this->assertSame(Post::TYPE_VIDEO, $post->type);
        $this->assertTrue($post->hasMedia('videos'));
    }
}
