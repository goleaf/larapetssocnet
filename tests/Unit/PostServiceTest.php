<?php

namespace Tests\Unit;

use App\Models\Post;
use App\Models\User;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_sets_text_type_when_no_media(): void
    {
        $user = User::factory()->create();
        $post = app(PostService::class)->create($user, [
            'body' => 'simple',
            'visibility' => Post::VISIBILITY_PUBLIC,
        ], []);

        $this->assertSame(Post::TYPE_TEXT, $post->type);
    }

    public function test_create_sets_video_type_when_video_present(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $post = app(PostService::class)->create(
            $user,
            ['visibility' => Post::VISIBILITY_PUBLIC],
            [UploadedFile::fake()->create('video.mp4', 200, 'video/mp4')]
        );

        $this->assertSame(Post::TYPE_VIDEO, $post->type);
    }
}
