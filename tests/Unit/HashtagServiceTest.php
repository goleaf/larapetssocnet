<?php

namespace Tests\Unit;

use App\Models\Hashtag;
use App\Models\Post;
use App\Models\User;
use App\Services\HashtagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HashtagServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_extracts_hashtags_from_post_body(): void
    {
        $service = app(HashtagService::class);

        $tags = $service->extract('Hello #Pets and #Cats #pets');

        $this->assertSame(['pets', 'cats'], $tags);
    }

    public function test_sync_hashtags_creates_and_attaches_tags(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create(['body' => 'Hello #pets #cats']);

        app(HashtagService::class)->syncHashtags($post);

        $this->assertCount(2, $post->fresh()->hashtags);
    }

    public function test_first_or_create_accepts_lazy_values_closure(): void
    {
        $existing = Hashtag::factory()->create([
            'name' => 'pets',
            'slug' => 'pets',
        ]);

        $closureExecuted = false;

        $hashtag = Hashtag::query()->firstOrCreate(
            ['name' => 'pets'],
            function () use (&$closureExecuted): array {
                $closureExecuted = true;

                return ['slug' => 'should-not-be-used'];
            }
        );

        $this->assertTrue($hashtag->is($existing));
        $this->assertFalse($closureExecuted);
        $this->assertSame('pets', $hashtag->slug);
    }
}
