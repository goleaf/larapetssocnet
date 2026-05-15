<?php

use App\Models\Content\Hashtag;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\ContentService;
use App\Services\HashtagService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows follower-only hashtag posts to accepted followers', function (): void {
    $author = User::factory()->create(['is_private' => false]);
    $viewer = User::factory()->create(['is_private' => false]);

    $viewer->following()->attach($author->getKey(), ['status' => 'accepted']);

    $publicBody = 'Public #cats post';
    $followersBody = 'Followers #cats post';

    $publicPost = Post::factory()->for($author)->create([
        'body' => $publicBody,
        'body_html' => app(ContentService::class)->process($publicBody),
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
    $followersPost = Post::factory()->for($author)->create([
        'body' => $followersBody,
        'body_html' => app(ContentService::class)->process($followersBody),
        'visibility' => Post::VISIBILITY_FOLLOWERS,
    ]);

    app(HashtagService::class)->syncHashtags($publicPost);
    app(HashtagService::class)->syncHashtags($followersPost);

    $hashtag = Hashtag::query()->where('normalized_name', 'cats')->firstOrFail();

    $this->actingAs($viewer)
        ->get(route('hashtags.show', $hashtag))
        ->assertSuccessful()
        ->assertSeeText('Public #cats post')
        ->assertSeeText('Followers #cats post');
});

it('hides follower-only hashtag posts from guests', function (): void {
    $author = User::factory()->create(['is_private' => false]);

    $publicBody = 'Public #cats post';
    $followersBody = 'Followers #cats post';

    $publicPost = Post::factory()->for($author)->create([
        'body' => $publicBody,
        'body_html' => app(ContentService::class)->process($publicBody),
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
    $followersPost = Post::factory()->for($author)->create([
        'body' => $followersBody,
        'body_html' => app(ContentService::class)->process($followersBody),
        'visibility' => Post::VISIBILITY_FOLLOWERS,
    ]);

    app(HashtagService::class)->syncHashtags($publicPost);
    app(HashtagService::class)->syncHashtags($followersPost);

    $hashtag = Hashtag::query()->where('normalized_name', 'cats')->firstOrFail();

    $this->get(route('hashtags.show', $hashtag))
        ->assertSuccessful()
        ->assertSeeText('Public #cats post')
        ->assertDontSeeText('Followers #cats post');
});

it('hides hashtag posts from blocked viewers', function (): void {
    $author = User::factory()->create(['is_private' => false]);
    $viewer = User::factory()->create(['is_private' => false]);

    $author->blocking()->attach($viewer->getKey());

    $body = 'Blocked #cats post';

    $post = Post::factory()->for($author)->create([
        'body' => $body,
        'body_html' => app(ContentService::class)->process($body),
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    app(HashtagService::class)->syncHashtags($post);

    $hashtag = Hashtag::query()->where('normalized_name', 'cats')->firstOrFail();

    $this->actingAs($viewer)
        ->get(route('hashtags.show', $hashtag))
        ->assertSuccessful()
        ->assertDontSeeText('Blocked #cats post');
});
