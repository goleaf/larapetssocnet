<?php

namespace Tests\Feature;

use App\Models\Content\Post;
use App\Models\Identity\User;
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
                'media' => [UploadedFile::fake()->image('photo.jpg', 800, 600)],
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
                'media' => [UploadedFile::fake()->create('clip.mp4', 1024, 'video/mp4')],
            ])->assertRedirect();

        $post = Post::query()->latest('id')->firstOrFail();

        $this->assertSame(Post::TYPE_VIDEO, $post->type);
        $this->assertTrue($post->hasMedia('videos'));
    }

    public function test_can_upload_mov_video_to_post(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'body' => null,
                'visibility' => 'public',
                'media' => [UploadedFile::fake()->create('clip.mov', 1024, 'video/quicktime')],
            ])->assertRedirect();

        $post = Post::query()->latest('id')->firstOrFail();
        $this->assertSame(Post::TYPE_VIDEO, $post->type);
        $this->assertCount(1, $post->getMedia('videos'));
    }

    public function test_rejects_webm_video_upload(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('posts.create'))
            ->post(route('posts.store'), [
                'body' => null,
                'visibility' => 'public',
                'media' => [UploadedFile::fake()->create('clip.webm', 1024, 'video/webm')],
            ]);

        $response->assertRedirect(route('posts.create'));
        $response->assertSessionHasErrors(['media.0']);
        $this->assertSame(0, Post::query()->count());
    }

    public function test_rejects_video_over_100mb(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('posts.create'))
            ->post(route('posts.store'), [
                'body' => null,
                'visibility' => 'public',
                'media' => [UploadedFile::fake()->create('clip.mp4', 102401, 'video/mp4')],
            ]);

        $response->assertRedirect(route('posts.create'));
        $response->assertSessionHasErrors(['media.0']);
        $this->assertSame(0, Post::query()->count());
    }

    public function test_rejects_non_video_file_as_video_upload(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('posts.create'))
            ->post(route('posts.store'), [
                'body' => null,
                'visibility' => 'public',
                'media' => [UploadedFile::fake()->create('doc.pdf', 256, 'application/pdf')],
            ]);

        $response->assertRedirect(route('posts.create'));
        $response->assertSessionHasErrors(['media.0']);
        $this->assertSame(0, Post::query()->count());
    }

    public function test_can_upload_photos_and_videos_together(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'body' => null,
                'visibility' => 'public',
                'media' => [
                    UploadedFile::fake()->image('photo.jpg', 800, 600),
                    UploadedFile::fake()->create('clip.mp4', 1024, 'video/mp4'),
                ],
            ])->assertRedirect();

        $post = Post::query()->latest('id')->firstOrFail();

        $this->assertSame(Post::TYPE_VIDEO, $post->type);
        $this->assertCount(1, $post->getMedia('photos'));
        $this->assertCount(1, $post->getMedia('videos'));
    }

    public function test_can_upload_multiple_videos_to_post(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'body' => null,
                'visibility' => 'public',
                'media' => [
                    UploadedFile::fake()->create('clip-1.mp4', 1024, 'video/mp4'),
                    UploadedFile::fake()->create('clip-2.mov', 1024, 'video/quicktime'),
                ],
            ])->assertRedirect();

        $post = Post::query()->latest('id')->firstOrFail();

        $this->assertSame(Post::TYPE_VIDEO, $post->type);
        $this->assertCount(2, $post->getMedia('videos'));
    }

    public function test_can_upload_ten_images_to_post(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $photos = array_map(
            fn (int $index): UploadedFile => UploadedFile::fake()->image("photo-{$index}.jpg", 800, 600),
            range(1, 10),
        );

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'body' => null,
                'visibility' => 'public',
                'media' => $photos,
            ])->assertRedirect();

        $post = Post::query()->latest('id')->firstOrFail();

        $this->assertSame(Post::TYPE_PHOTO, $post->type);
        $this->assertCount(10, $post->getMedia('photos'));
    }

    public function test_rejects_more_than_ten_attachments(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $photos = array_map(
            fn (int $index): UploadedFile => UploadedFile::fake()->image("photo-{$index}.jpg", 800, 600),
            range(1, 11),
        );

        $response = $this->actingAs($user)
            ->from(route('posts.create'))
            ->post(route('posts.store'), [
                'body' => null,
                'visibility' => 'public',
                'media' => $photos,
            ]);

        $response->assertRedirect(route('posts.create'));
        $response->assertSessionHasErrors(['media']);
        $this->assertSame(0, Post::query()->count());
    }

    public function test_rejects_photo_over_10mb(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('posts.create'))
            ->post(route('posts.store'), [
                'body' => null,
                'visibility' => 'public',
                'media' => [UploadedFile::fake()->image('large-photo.jpg')->size(10241)],
            ]);

        $response->assertRedirect(route('posts.create'));
        $response->assertSessionHasErrors(['media.0']);
        $this->assertSame(0, Post::query()->count());
    }

    public function test_post_media_records_are_soft_deleted_when_post_is_deleted(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'body' => null,
                'visibility' => 'public',
                'media' => [UploadedFile::fake()->image('photo.jpg', 800, 600)],
            ])
            ->assertRedirect();

        $post = Post::query()->latest('id')->firstOrFail();
        $media = $post->getFirstMedia('photos');

        $this->assertNotNull($media);

        $relativePath = $media->getPathRelativeToRoot();
        Storage::disk('public')->assertExists($relativePath);

        $this->actingAs($user)
            ->delete(route('posts.destroy', $post))
            ->assertRedirect();

        Storage::disk('public')->assertExists($relativePath);
        $this->assertSoftDeleted('posts', [
            'id' => $post->id,
        ]);
        $this->assertSoftDeleted('post_media', [
            'post_id' => $post->id,
        ]);
        $this->assertDatabaseHas('media', [
            'id' => $media->id,
        ]);
    }
}
