<?php

namespace Tests\Feature;

use App\Models\Hashtag;
use App\Models\Post;
use App\Models\User;
use App\Services\HashtagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HashtagPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_hashtag_page_shows_all_related_visible_posts(): void
    {
        $author = User::factory()->create(['is_private' => false]);

        $postOne = Post::query()->create([
            'user_id' => $author->id,
            'body' => 'First #cats post',
            'visibility' => Post::VISIBILITY_PUBLIC,
            'status' => 'published',
        ]);

        $postTwo = Post::query()->create([
            'user_id' => $author->id,
            'body' => 'Second #cats post',
            'visibility' => Post::VISIBILITY_PUBLIC,
            'status' => 'published',
        ]);

        $otherPost = Post::query()->create([
            'user_id' => $author->id,
            'body' => 'Only #dogs here',
            'visibility' => Post::VISIBILITY_PUBLIC,
            'status' => 'published',
        ]);

        app(HashtagService::class)->syncHashtags($postOne);
        app(HashtagService::class)->syncHashtags($postTwo);
        app(HashtagService::class)->syncHashtags($otherPost);

        $hashtag = Hashtag::query()->where('name', 'cats')->firstOrFail();

        $this->get(route('hashtags.show', $hashtag))
            ->assertOk()
            ->assertSee('First #cats post')
            ->assertSee('Second #cats post')
            ->assertDontSee('Only #dogs here');
    }

    public function test_hashtag_page_hides_private_posts_from_guests(): void
    {
        $privateAuthor = User::factory()->create(['is_private' => true]);
        $publicAuthor = User::factory()->create(['is_private' => false]);

        $privateTaggedPost = Post::query()->create([
            'user_id' => $privateAuthor->id,
            'body' => 'Secret #cats post',
            'visibility' => Post::VISIBILITY_PUBLIC,
            'status' => 'published',
        ]);

        $publicTaggedPost = Post::query()->create([
            'user_id' => $publicAuthor->id,
            'body' => 'Public #cats post',
            'visibility' => Post::VISIBILITY_PUBLIC,
            'status' => 'published',
        ]);

        app(HashtagService::class)->syncHashtags($privateTaggedPost);
        app(HashtagService::class)->syncHashtags($publicTaggedPost);

        $hashtag = Hashtag::query()->where('name', 'cats')->firstOrFail();

        $this->get(route('hashtags.show', $hashtag))
            ->assertOk()
            ->assertSee('Public #cats post')
            ->assertDontSee('Secret #cats post');
    }
}

